<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'proof_required' => 'boolean',
            'flagged' => 'boolean',
            'paid_at' => 'datetime',
            'proof_expires_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->access_token ??= Str::random(40);
        });

        static::created(function (Order $order) {
            $order->forceFill(['number' => 'POD-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /** The link customers use to follow an order, including guests who have no account. */
    public function url(): string
    {
        return route('orders.show', ['order' => $this->number, 't' => $this->access_token]);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isDelivery(): bool
    {
        return $this->fulfilment === 'delivery';
    }

    /** Orders the print shop can see: released for production. */
    public function scopeReleased(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(fn (OrderStatus $s) => $s->value, OrderStatus::released()));
    }

    public function record(string $type, string $message, ?int $userId = null): OrderEvent
    {
        return $this->events()->create(['type' => $type, 'message' => $message, 'user_id' => $userId]);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
