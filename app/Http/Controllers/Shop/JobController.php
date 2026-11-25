<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderArea;
use App\Services\JobSheet;
use App\Services\OrderService;
use App\Services\ProofImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The print shop's view: only orders that have been released for production
 * (paid, and proof-approved where a proof applies), and never prices.
 */
class JobController extends Controller
{
    public const FILTERS = [
        'active' => 'To do',
        'queued' => 'Queued',
        'printing' => 'Printing',
        'ready' => 'Ready',
        'done' => 'Collected or sent',
    ];

    public function index(Request $request): View
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : 'active';

        $query = Order::query()->released()->with('lines');

        match ($filter) {
            'active' => $query->whereIn('production_stage', ['queued', 'printing'])->where('status', OrderStatus::InProduction->value),
            'queued', 'printing' => $query->where('production_stage', $filter)->where('status', OrderStatus::InProduction->value),
            'ready' => $query->where('status', OrderStatus::Ready->value),
            'done' => $query->whereIn('status', [OrderStatus::Dispatched->value, OrderStatus::PickedUp->value, OrderStatus::Completed->value]),
        };

        return view('shop.jobs', [
            'orders' => $query->orderBy('due_date')->orderBy('id')->get(),
            'filter' => $filter,
            'filters' => self::FILTERS,
            'counts' => [
                'active' => Order::released()->where('status', OrderStatus::InProduction->value)->count(),
                'ready' => Order::where('status', OrderStatus::Ready->value)->count(),
            ],
        ]);
    }

    public function show(Order $order, JobSheet $sheet): View
    {
        $this->authorizeJob($order);
        $order->load(['lines.items', 'lines.areas']);

        return view('shop.job', ['order' => $order, 'sheet' => $sheet]);
    }

    public function stage(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeJob($order);
        $data = $request->validate(['stage' => ['required', 'in:queued,printing,ready']]);

        $orders->setStage($order, $data['stage'], $request->user()->id);

        return back()->with('status', 'Job updated.');
    }

    public function sheet(Order $order, JobSheet $sheet): Response
    {
        $this->authorizeJob($order);

        return response($sheet->pdf($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$order->number.'-job-sheet.pdf"',
        ]);
    }

    public function files(Order $order, JobSheet $sheet): BinaryFileResponse
    {
        $this->authorizeJob($order);

        return response()->download($sheet->zip($order), $order->number.'-print-files.zip')->deleteFileAfterSend();
    }

    /** A single print-ready PNG. */
    public function file(Order $order, OrderArea $area, JobSheet $sheet): StreamedResponse
    {
        $this->authorizeJob($order);
        abort_unless($area->line->order_id === $order->id, 404);

        return Storage::disk('designs')->download($area->export_path, $order->number.'-'.$sheet->areaFile($area));
    }

    /** The artwork placed on the product, for the on-screen job page. */
    public function placement(Order $order, OrderArea $area, ProofImage $placements): Response
    {
        $this->authorizeJob($order);
        abort_unless($area->line->order_id === $order->id, 404);

        return response($placements->placement($area), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=300']);
    }

    private function authorizeJob(Order $order): void
    {
        abort_unless(in_array($order->status, OrderStatus::released(), true), 404);
    }
}
