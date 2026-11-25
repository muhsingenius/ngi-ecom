<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceTier extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['min_qty' => 'integer', 'unit_price' => 'decimal:2'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
