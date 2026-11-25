<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class Design extends Model
{
    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(ProductColour::class, 'product_colour_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cartLines(): HasMany
    {
        return $this->hasMany(CartLine::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(DesignArea::class);
    }

    /** Designs belong to the signed-in customer, or to the browser session that made them. */
    public function scopeOwnedBy(Builder $query, Request $request): Builder
    {
        $token = $request->session()->get('designer_token');
        $user = $request->user();

        return $query->where(function (Builder $q) use ($user, $token) {
            $q->whereRaw('1 = 0');

            if ($user) {
                $q->orWhere('user_id', $user->id);
            }

            if ($token) {
                $q->orWhere(fn ($w) => $w->whereNull('user_id')->where('session_token', $token));
            }
        });
    }

    public function isOwnedBy(Request $request): bool
    {
        return self::query()->whereKey($this->getKey())->ownedBy($request)->exists();
    }
}
