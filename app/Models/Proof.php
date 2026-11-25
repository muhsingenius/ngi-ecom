<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Proof extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['replaced' => 'boolean', 'sent_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Proof $proof) => $proof->token ??= Str::random(40));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function url(): string
    {
        return route('proofs.show', $this->token);
    }

    public function isOpen(): bool
    {
        return $this->status === 'sent';
    }
}
