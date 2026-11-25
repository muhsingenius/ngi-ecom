<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class CartLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

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

    public function quantity(): int
    {
        return (int) collect($this->items)->sum('qty');
    }
}
