@php
    use App\Support\Money;

    $example = \App\Models\Product::active()->with('tiers')->orderBy('sort')->orderBy('id')->first();
    $single = $example ? Money::toMinor($example->singlePrice()) : null;
    $lowest = $example ? Money::toMinor($example->tiers->min('unit_price')) : null;
    $top = $example?->tiers->sortByDesc('min_qty')->first();
@endphp

<section id="groups" class="section">
    <div class="container-page">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="type-title">Made for one, priced for many</h2>
            <p class="type-lead mt-4">Whether it is one T-shirt or a hundred, you see the price before you pay, and it drops as the quantity goes up.</p>
        </div>

        <div class="mt-12 grid gap-6 lg:grid-cols-2">
            <div class="rounded-card bg-mist p-8">
                <h3 class="type-heading text-xl">Just for you</h3>
                <ul class="mt-5 space-y-3 text-copy">
                    @foreach (['Design it on your phone in minutes: upload a picture, add your words, pick colours.', 'See exactly how it will look on the product before you order.', 'Pay by card or Mobile Money, then collect it or have it delivered.'] as $point)
                        <li class="flex gap-3"><x-icon name="check" class="mt-0.5 size-5 text-brand" /> <span>{{ $point }}</span></li>
                    @endforeach
                </ul>
                <a href="{{ route('catalogue.index') }}" class="btn btn-brand btn-lg mt-8">Start designing</a>
            </div>

            <div class="rounded-card bg-night p-8 text-[#aeb7c5]">
                <h3 class="type-heading text-xl text-white">For your school, church, team or company</h3>
                <ul class="mt-5 space-y-3">
                    @foreach ([
                        'Bulk prices for every product, published up front. No quote requests, no waiting.',
                        'Sizes and colours of one design count together towards your discount.',
                        'Add a name and number for each person, or upload a list.',
                        'Large orders get a proof to approve before anything is printed.',
                    ] as $point)
                        <li class="flex gap-3"><x-icon name="check" class="mt-0.5 size-5 text-accent" /> <span>{{ $point }}</span></li>
                    @endforeach
                </ul>

                @if ($example && $top && $lowest < $single)
                    <p class="mt-6 rounded-control bg-white/5 p-4 text-sm ring-1 ring-white/10">
                        For example, our {{ strtolower($example->name) }} is <span class="font-semibold text-white">{{ Money::format($single) }}</span> for one and
                        <span class="font-semibold text-accent">{{ Money::format($lowest) }}</span> each from {{ $top->min_qty }}.
                    </p>
                @endif

                <a href="{{ route('catalogue.index') }}" class="btn btn-accent btn-lg mt-8">See bulk prices</a>
            </div>
        </div>
    </div>
</section>
