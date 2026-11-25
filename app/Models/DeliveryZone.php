<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['bands' => 'array', 'active' => 'boolean', 'flat_fee' => 'decimal:2', 'free_threshold' => 'decimal:2'];
    }
}
