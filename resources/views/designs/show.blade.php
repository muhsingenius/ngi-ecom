<x-layouts.app title="Your design">
    <section class="section">
        <div class="container-page">
            <span class="pill bg-brand-tint shadow-none"><x-icon name="check" class="size-4" /> Design saved</span>
            <h1 class="type-title mt-4">{{ $design->name ?: 'Your '.strtolower($design->product->type->label()).' design' }}</h1>
            <p class="type-lead mt-2">{{ $design->product->name }} · {{ $design->product->quality }}@if ($design->colour) · {{ $design->colour->name }}@endif</p>

            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($design->areas as $area)
                    <figure class="panel p-4">
                        <div class="flex aspect-square items-center justify-center rounded-control bg-[repeating-conic-gradient(#f0f1f5_0%_25%,#fff_0%_50%)] bg-[length:16px_16px]">
                            @if ($previews[$area->id])
                                <img src="{{ $previews[$area->id] }}" alt="{{ $area->printArea->name }} print" class="max-h-full max-w-full object-contain">
                            @endif
                        </div>
                        <figcaption class="mt-3 text-sm">
                            <span class="font-semibold text-ink">{{ $area->printArea->name }}</span>
                            <span class="text-muted"> · {{ $area->export_width }} × {{ $area->export_height }} px · {{ $area->export_dpi }} DPI</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('designs.edit', $design) }}" class="btn btn-outline btn-lg">Edit design</a>
                <a href="{{ route('catalogue.show', $design->product) }}" class="link-arrow self-center">Back to the product</a>
            </div>
        </div>
    </section>
</x-layouts.app>
