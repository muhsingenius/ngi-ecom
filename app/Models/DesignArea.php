<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignArea extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['personalisation' => 'array'];
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function printArea(): BelongsTo
    {
        return $this->belongsTo(PrintArea::class);
    }
}
