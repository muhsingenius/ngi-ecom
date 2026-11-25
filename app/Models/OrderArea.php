<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderArea extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['personalisation' => 'array', 'assets' => 'array', 'width_cm' => 'float', 'height_cm' => 'float'];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }
}
