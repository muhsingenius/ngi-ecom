<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case AwaitingProof = 'awaiting_proof';
    case ProofApproved = 'proof_approved';
    case InProduction = 'in_production';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case PickedUp = 'picked_up';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Paid => 'Paid',
            self::AwaitingProof => 'Awaiting proof approval',
            self::ProofApproved => 'Proof approved',
            self::InProduction => 'In production',
            self::Ready => 'Ready',
            self::Dispatched => 'Dispatched',
            self::PickedUp => 'Picked up',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /** Colour family for badges in the UI. */
    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment, self::AwaitingProof => 'amber',
            self::Cancelled, self::Refunded => 'red',
            self::Completed, self::PickedUp, self::Dispatched, self::Ready => 'green',
            default => 'blue',
        };
    }

    /** @return list<self> */
    public static function released(): array
    {
        return [self::InProduction, self::Ready, self::Dispatched, self::PickedUp, self::Completed];
    }
}
