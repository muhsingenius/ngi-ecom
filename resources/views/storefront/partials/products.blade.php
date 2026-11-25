{{-- Plain cards for now: they become links once the catalogue exists. --}}
<section id="products" class="section">
    <div class="container-page">
        <div class="max-w-2xl">
            <h2 class="type-title">Products you can print on</h2>
            <p class="type-lead mt-4">Pick something to start with. More products are added as the catalogue grows.</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['tshirt', 'T-shirts', 'Wear your own design.'],
                ['mug', 'Mugs', 'Everyday gifts, made personal.'],
                ['hoodie', 'Hoodies', 'Warm layers with your print.'],
                ['cap', 'Caps', 'Headwear with your mark.'],
                ['tote', 'Tote bags', 'Carry your design around.'],
                ['stickers', 'Stickers', 'Small prints, big impact.'],
            ] as [$art, $name, $blurb])
                <article class="card">
                    <div class="flex h-60 items-center justify-center bg-gradient-to-b from-mist to-[#eceff7] p-2">
                        <x-product-art :name="$art" class="h-full w-auto" />
                    </div>
                    <div class="bg-mist px-6 pb-6 pt-5">
                        <h3 class="type-heading">{{ $name }}</h3>
                        <p class="mt-1 text-sm text-copy">{{ $blurb }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
