<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\TierScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'tier_scope_override' => TierScope::class,
            'active' => 'boolean',
        ];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class)->orderBy('min_qty');
    }

    public function colours(): HasMany
    {
        return $this->hasMany(ProductColour::class)->orderBy('sort')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function printAreas(): HasMany
    {
        return $this->hasMany(PrintArea::class)->orderBy('sort')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image) {
            return null;
        }

        // Bundled site photos live under public/media; uploads and generated mockups on the public disk.
        return str_starts_with($this->image, 'media/') ? asset($this->image) : Storage::disk('public')->url($this->image);
    }

    /** The price for a single item (the first tier). */
    public function singlePrice(): float
    {
        return (float) ($this->tiers->firstWhere('min_qty', 1)?->unit_price ?? $this->tiers->first()?->unit_price ?? 0);
    }

    /** Sizes offered, in display order, taken from active variants. */
    public function sizes(): array
    {
        return $this->variants->where('active', true)->pluck('size')->unique()->values()->all();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
