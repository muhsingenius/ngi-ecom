<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLine;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Simple sales reports: orders and revenue by day or week, and the top products.
 * Revenue counts paid orders on the day they were paid, less anything refunded.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $by = $request->query('by') === 'week' ? 'week' : 'day';

        $placed = Order::whereBetween('created_at', [$from, $to])->whereNot('status', OrderStatus::Cancelled->value)->get(['id', 'created_at', 'quantity']);
        $paid = Order::with('refunds')->whereBetween('paid_at', [$from, $to])->get();

        $bucket = fn (Carbon $date) => $by === 'week' ? $date->copy()->startOfWeek()->toDateString() : $date->toDateString();

        $rows = [];
        $cursor = $by === 'week' ? $from->copy()->startOfWeek() : $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $rows[$cursor->toDateString()] = ['label' => $by === 'week' ? 'Week of '.$cursor->format('j M') : $cursor->format('D j M'), 'orders' => 0, 'items' => 0, 'revenue' => 0];
            $cursor->addDays($by === 'week' ? 7 : 1);
        }

        foreach ($placed as $order) {
            $key = $bucket($order->created_at);

            if (isset($rows[$key])) {
                $rows[$key]['orders']++;
                $rows[$key]['items'] += $order->quantity;
            }
        }

        foreach ($paid as $order) {
            $key = $bucket($order->paid_at);

            if (isset($rows[$key])) {
                $rows[$key]['revenue'] += Money::toMinor($order->total) - Money::toMinor($order->refunds->whereIn('status', ['pending', 'processed', 'recorded'])->sum('amount'));
            }
        }

        $top = OrderLine::query()
            ->selectRaw('product_name, quality, SUM(quantity) as units, SUM(line_total) as revenue, COUNT(DISTINCT order_id) as orders')
            ->whereHas('order', fn ($q) => $q->whereNotNull('paid_at')->whereBetween('paid_at', [$from, $to]))
            ->groupBy('product_name', 'quality')
            ->orderByDesc('units')
            ->limit(10)
            ->get();

        return view('admin.reports', [
            'rows' => array_values($rows),
            'top' => $top,
            'from' => $from,
            'to' => $to,
            'by' => $by,
            'totals' => [
                'orders' => $placed->count(),
                'items' => (int) $placed->sum('quantity'),
                'revenue' => array_sum(array_column($rows, 'revenue')),
                'paid' => $paid->count(),
            ],
            'maxRevenue' => max(1, max(array_column($rows, 'revenue') ?: [0])),
        ]);
    }
}
