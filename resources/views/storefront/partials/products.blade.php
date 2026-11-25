@php
    $cards = [
        ['tshirt.webp', 'T-shirts', 'Wear your own design.', 'center top', 'tshirt'],
        ['mug.jpg', 'Mugs and tumblers', 'Everyday gifts, made personal.', 'center', null],
        ['hoodie.webp', 'Hoodies', 'Warm layers with your print.', 'center', 'hoodie'],
        ['cap.webp', 'Caps', 'Headwear with your mark.', 'center', 'cap'],
        ['tote_bag.webp', 'Tote bags', 'Carry your design around.', 'center', null],
        ['sticker.webp', 'Stickers', 'Small prints, big impact.', 'center', null],
    ];
@endphp

<section id="products" class="section">
    <div class="container-page">
        <div class="max-w-2xl">
            <h2 class="type-title">Products you can print on</h2>
            <p class="type-lead mt-4">Pick something to start with. Prices drop as your quantity goes up.</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cards as [$file, $name, $blurb, $pos, $type])
                <article class="card relative">
                    @if ($type ?? null)
                        <a href="{{ route('catalogue.index', ['type' => $type]) }}" class="absolute inset-0 z-10" aria-label="Shop {{ $name }}"></a>
                    @endif
                    <img src="{{ asset('media/'.$file) }}" alt="{{ $name }}" loading="lazy" width="600" height="750"
                         class="aspect-[4/5] w-full bg-white object-cover" style="object-position: {{ $pos }}">
                    <div class="bg-mist px-6 pb-6 pt-5">
                        <h3 class="type-heading">{{ $name }}</h3>
                        <p class="mt-1 text-sm text-copy">{{ $blurb }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
