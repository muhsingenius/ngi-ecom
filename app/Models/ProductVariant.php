<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['size_surcharge' => 'decimal:2', 'active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(ProductColour::class, 'product_colour_id');
    }
}
