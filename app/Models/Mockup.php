<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Mockup extends Model
{
    protected $guarded = [];

    public function colour(): BelongsTo
    {
        return $this->belongsTo(ProductColour::class, 'product_colour_id');
    }

    public function printArea(): BelongsTo
    {
        return $this->belongsTo(PrintArea::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
