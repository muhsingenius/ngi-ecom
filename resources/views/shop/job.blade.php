@php
    $stages = ['queued' => 'Queued', 'printing' => 'Printing', 'ready' => 'Ready'];
    $current = $order->status->value === 'ready' ? 'ready' : $order->production_stage;
    $canMove = in_array($order->status->value, ['in_production', 'ready'], true);
@endphp

<x-layouts.app :title="'Job '.$order->number">
    <section class="section">
        <div class="container-page">
            <a href="{{ route('shop.jobs') }}" class="link-arrow text-sm">← All jobs</a>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="type-title">{{ $order->number }}</h1>
                    <p class="type-lead mt-1">{{ $order->quantity }} {{ \Illuminate\Support\Str::plural('item', $order->quantity) }} · {{ $order->due_date ? 'Due '.$order->due_date->format('D j M Y') : 'No due date' }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('shop.jobs.sheet', $order) }}" class="btn btn-outline">Job sheet (PDF)</a>
                    <a href="{{ route('shop.jobs.files', $order) }}" class="btn btn-brand">Download all print files (ZIP)</a>
                </div>
            </div>

            @if (session('status'))<p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm font-semibold text-brand-strong" role="status">{{ session('status') }}</p>@endif

            @if ($canMove)
                <div class="panel mt-6 p-5">
                    <p class="text-sm font-semibold text-ink">Status</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($stages as $key => $label)
                            <form method="POST" action="{{ route('shop.jobs.stage', $order) }}">
                                @csrf
                                <input type="hidden" name="stage" value="{{ $key }}">
                                <button @class(['btn', 'btn-brand' => $current === $key, 'btn-outline' => $current !== $key])>{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-muted">Marking a job Ready emails the customer. The store marks it dispatched or picked up.</p>
                </div>
            @else
                <p class="mt-6 rounded-control bg-mist px-4 py-3 text-sm text-copy">This job is {{ strtolower($order->status->label()) }}.</p>
            @endif

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <div class="panel p-5 text-sm">
                    <h2 class="type-heading text-base">Customer</h2>
                    <p class="mt-2 font-semibold text-ink">{{ $order->customer_name }}</p>
                    <p class="text-copy"><a href="tel:{{ $order->phone }}" class="underline">{{ $order->phone }}</a></p>
                </div>
                <div class="panel p-5 text-sm">
                    <h2 class="type-heading text-base">{{ $order->isDelivery() ? 'Delivery' : 'Pickup' }}</h2>
                    @if ($order->isDelivery())
                        <p class="mt-2 font-semibold text-ink">{{ $order->zone_name }}</p>
                        <p class="whitespace-pre-line text-copy">{{ $order->delivery_address }}</p>
                    @else
                        <p class="mt-2 font-semibold text-ink">{{ $order->pickup_name }}</p>
                        <p class="whitespace-pre-line text-copy">{{ $order->pickup_address }}</p>
                    @endif
                </div>
            </div>

            @if ($order->notes)<p class="mt-6 rounded-control bg-accent-tint px-4 py-3 text-sm text-ink"><span class="font-semibold">Order notes:</span> {{ $order->notes }}</p>@endif

            @foreach ($order->lines as $line)
                <div class="panel mt-6 p-5">
                    <h2 class="type-heading">{{ $loop->iteration }}. {{ $line->product_name }} <span class="font-normal text-muted">· {{ $line->quality }} · {{ $line->quantity }} {{ \Illuminate\Support\Str::plural('item', $line->quantity) }}</span></h2>
                    @if ($line->notes)<p class="mt-2 text-sm text-ink"><span class="font-semibold">Notes:</span> {{ $line->notes }}</p>@endif

                    <div class="mt-4 overflow-x-auto">
                        <table class="text-sm">
                            <thead class="text-left text-muted"><tr><th class="py-1 pr-6 font-semibold">Colour</th><th class="py-1 pr-6 font-semibold">Size breakdown</th></tr></thead>
                            <tbody>
                                @foreach ($line->items->groupBy('colour_name') as $colour => $items)
                                    <tr class="border-t border-line"><td class="py-2 pr-6 font-semibold text-ink"><span class="mr-2 inline-block size-3 rounded-full border border-line align-middle" style="background: {{ $items->first()->colour_hex }}"></span>{{ $colour }}</td>
                                        <td class="py-2 pr-6 text-copy">{{ $items->groupBy('size')->map(fn ($g, $s) => $g->sum('qty').' × '.$s)->implode(', ') }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($line->areas as $area)
                            <figure class="rounded-control border border-line p-3">
                                <img src="{{ route('shop.jobs.placement', [$order, $area]) }}" alt="{{ $area->area_name }} placement" class="w-full rounded-chip">
                                <figcaption class="mt-3 text-sm">
                                    <p class="font-semibold text-ink">{{ $area->area_name }}</p>
                                    <p class="text-copy">{{ strtoupper($area->method) }} · {{ rtrim(rtrim(number_format($area->width_cm, 1), '0'), '.') }} × {{ rtrim(rtrim(number_format($area->height_cm, 1), '0'), '.') }} cm · {{ $area->export_dpi }} DPI</p>
                                    @foreach ($area->personalisation ?? [] as $role => $p)
                                        <p class="text-copy">{{ ucfirst($role) }}: {{ $p['font'] }}, {{ $p['font_height_mm'] }} mm, {{ $p['x_cm'] }} cm from the left, {{ $p['y_cm'] }} cm from the top</p>
                                    @endforeach
                                    <a href="{{ route('shop.jobs.file', [$order, $area]) }}" class="link-arrow mt-2 text-sm">Download PNG</a>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>

                    @if ($line->items->contains(fn ($i) => $i->name || $i->number))
                        <h3 class="mt-6 font-semibold text-ink">Names and numbers ({{ $line->items->count() }})</h3>
                        <div class="mt-2 overflow-x-auto rounded-control border border-line">
                            <table class="w-full min-w-[22rem] text-left text-sm">
                                <thead class="bg-mist text-muted"><tr><th class="px-3 py-2 font-semibold">#</th><th class="px-3 py-2 font-semibold">Name</th><th class="px-3 py-2 font-semibold">Number</th><th class="px-3 py-2 font-semibold">Size</th><th class="px-3 py-2 font-semibold">Colour</th></tr></thead>
                                <tbody>@foreach ($line->items as $item)<tr class="border-t border-line"><td class="px-3 py-2 text-muted">{{ $loop->iteration }}</td><td class="px-3 py-2 font-semibold text-ink">{{ $item->name }}</td><td class="px-3 py-2 font-semibold text-ink">{{ $item->number }}</td><td class="px-3 py-2">{{ $item->size }}</td><td class="px-3 py-2">{{ $item->colour_name }}</td></tr>@endforeach</tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.app>
