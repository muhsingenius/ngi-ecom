<?php

namespace App\Models;

use App\Enums\PrintMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintArea extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'method' => PrintMethod::class,
            'width_cm' => 'float',
            'height_cm' => 'float',
            'min_line_mm' => 'float',
            'extra_fee' => 'decimal:2',
            'allows_name' => 'boolean',
            'allows_number' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mockups(): HasMany
    {
        return $this->hasMany(Mockup::class);
    }

    public function allowsPersonalisation(): bool
    {
        return $this->allows_name || $this->allows_number;
    }

    /** Pixel size of a print-ready export at 300 DPI. */
    public function exportPixels(int $dpi = 300): array
    {
        return [(int) round($this->width_cm / 2.54 * $dpi), (int) round($this->height_cm / 2.54 * $dpi)];
    }
}
