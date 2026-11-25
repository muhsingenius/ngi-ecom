<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Job sheet {{ $order->number }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #12141c; }
        h1 { font-size: 22px; margin: 0 0 2px; }
        h2 { font-size: 14px; margin: 18px 0 6px; border-bottom: 2px solid #12141c; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; border: 1px solid #cfd3dc; vertical-align: top; }
        th { background: #f1f2f6; }
        .meta td { border: 0; padding: 2px 6px 2px 0; }
        .muted { color: #6f7587; }
        .note { background: #fff4dc; padding: 6px 8px; margin: 8px 0; }
        .area { width: 31%; display: inline-block; vertical-align: top; margin: 0 1% 10px 0; }
        .area img { width: 100%; border: 1px solid #cfd3dc; }
        .pagebreak { page-break-before: always; }
    </style>
</head>
<body>
    <h1>Job sheet {{ $order->number }}</h1>
    <p class="muted">Due {{ $order->due_date ? $order->due_date->format('D j M Y') : 'as soon as possible' }} &middot; {{ $order->quantity }} {{ \Illuminate\Support\Str::plural('item', $order->quantity) }}</p>

    <table class="meta">
        <tr><td><strong>Customer</strong></td><td>{{ $order->customer_name }}</td><td><strong>Phone</strong></td><td>{{ $order->phone }}</td></tr>
        <tr><td><strong>{{ $order->isDelivery() ? 'Delivery' : 'Pickup' }}</strong></td>
            <td colspan="3">{{ $order->isDelivery() ? $order->zone_name.' — '.$order->delivery_address : $order->pickup_name.' — '.$order->pickup_address }}</td></tr>
    </table>

    @if ($order->notes)<div class="note"><strong>Order notes:</strong> {{ $order->notes }}</div>@endif

    @foreach ($order->lines as $line)
        <div @if (! $loop->first) class="pagebreak" @endif>
            <h2>{{ $loop->iteration }}. {{ $line->product_name }} ({{ $line->quality }}) &mdash; {{ $line->quantity }} {{ \Illuminate\Support\Str::plural('item', $line->quantity) }}</h2>
            @if ($line->notes)<div class="note"><strong>Notes:</strong> {{ $line->notes }}</div>@endif

            <table>
                <tr><th>Colour</th><th>Size breakdown</th></tr>
                @foreach ($line->items->groupBy('colour_name') as $colour => $items)
                    <tr><td>{{ $colour }}</td><td>{{ $items->groupBy('size')->map(fn ($g, $s) => $g->sum('qty').' x '.$s)->implode(',  ') }}</td></tr>
                @endforeach
            </table>

            <h3>Print areas</h3>
            @foreach ($line->areas as $area)
                <div class="area">
                    <img src="{{ $images[$area->id] }}" alt="">
                    <strong>{{ $area->area_name }}</strong><br>
                    {{ strtoupper($area->method) }} &middot; {{ rtrim(rtrim(number_format($area->width_cm, 1), '0'), '.') }} x {{ rtrim(rtrim(number_format($area->height_cm, 1), '0'), '.') }} cm<br>
                    <span class="muted">Print file {{ $area->export_width }} x {{ $area->export_height }} px, {{ $area->export_dpi }} DPI</span>
                    @foreach ($area->personalisation ?? [] as $role => $p)
                        <br><span>{{ ucfirst($role) }}: {{ $p['font'] }}, {{ $p['font_height_mm'] }} mm, {{ $p['x_cm'] }} cm from left, {{ $p['y_cm'] }} cm from top</span>
                    @endforeach
                </div>
            @endforeach

            @if ($line->items->contains(fn ($i) => $i->name || $i->number))
                <h3>Names and numbers ({{ $line->items->count() }})</h3>
                <table>
                    <tr><th>#</th><th>Name</th><th>Number</th><th>Size</th><th>Colour</th></tr>
                    @foreach ($line->items as $item)
                        <tr><td>{{ $loop->iteration }}</td><td>{{ $item->name }}</td><td>{{ $item->number }}</td><td>{{ $item->size }}</td><td>{{ $item->colour_name }}</td></tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
