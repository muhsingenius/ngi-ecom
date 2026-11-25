<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-proofs')]
#[Description('Cancel proof-then-pay orders that were approved but not paid within the expiry window')]
class ExpireUnpaidProofs extends Command
{
    public function handle(OrderService $orders, Settings $settings): int
    {
        $days = $settings->int('proof.unpaid_expiry_days') ?? 7;
        $count = 0;

        Order::query()
            ->where('status', OrderStatus::ProofApproved->value)
            ->whereNull('paid_at')
            ->whereNull('cancelled_at')
            ->where('proof_expires_at', '<=', now())
            ->each(function (Order $order) use ($orders, $days, &$count) {
                $orders->cancel($order, "The proof was approved but the order was not paid within {$days} days");
                $count++;
            });

        $this->info("Cancelled {$count} unpaid order(s).");

        return self::SUCCESS;
    }
}
