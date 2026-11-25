<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $count = fn (OrderStatus $s) => Order::where('status', $s->value)->count();

        return view('admin.dashboard', [
            'cards' => [
                ['Awaiting proof approval', $count(OrderStatus::AwaitingProof), ['status' => 'awaiting_proof']],
                ['Needs follow-up', Order::where('flagged', true)->count(), ['flagged' => 1]],
                ['Ready to dispatch or collect', $count(OrderStatus::Ready), ['status' => 'ready']],
                ['In production', $count(OrderStatus::InProduction), ['status' => 'in_production']],
                ['Waiting for payment', $count(OrderStatus::PendingPayment) + $count(OrderStatus::ProofApproved), ['status' => 'pending_payment']],
            ],
            'recent' => Order::latest('id')->limit(8)->get(),
        ]);
    }
}
