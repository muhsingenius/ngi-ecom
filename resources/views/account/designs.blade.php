<x-layouts.app title="My designs">
    <section class="section">
        <div class="container-page">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="type-title">My saved designs</h1>
                <a href="{{ route('account.orders') }}" class="link-arrow">My orders</a>
            </div>

            @if (session('status'))<p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm font-semibold text-brand-strong" role="status">{{ session('status') }}</p>@endif

            @if ($designs->isEmpty())
                <div class="mt-8 rounded-card bg-mist p-8 text-center"><p class="text-copy">You have not saved a design yet.</p><a href="{{ route('catalogue.index') }}" class="btn btn-brand btn-lg mt-4">Start designing</a></div>
            @else
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($designs as $design)
                        <figure class="panel p-4">
                            <div class="flex aspect-square items-center justify-center rounded-control bg-[repeating-conic-gradient(#f0f1f5_0%_25%,#fff_0%_50%)] bg-[length:16px_16px]">
                                @if ($previews[$design->id])<img src="{{ $previews[$design->id] }}" alt="" class="max-h-full max-w-full object-contain">@endif
                            </div>
                            <figcaption class="mt-3">
                                <p class="font-semibold text-ink">{{ $design->name ?: 'Untitled design' }}</p>
                                <p class="text-sm text-muted">{{ $design->product->name }} · {{ $design->product->quality }}@if ($design->colour) · {{ $design->colour->name }}@endif</p>
                                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                                    <a href="{{ route('designs.order', $design) }}" class="link-arrow">Order</a>
                                    <a href="{{ route('designs.edit', $design) }}" class="link-arrow">Edit</a>
                                    <form method="POST" action="{{ route('account.designs.destroy', $design) }}" onsubmit="return confirm('Delete this design?')">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Delete</button></form>
                                </div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
