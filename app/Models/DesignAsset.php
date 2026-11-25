<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DesignAsset extends Model
{
    protected $guarded = [];

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
}
