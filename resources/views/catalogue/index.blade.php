@php use App\Support\Money; @endphp

<x-layouts.app title="Products">
    <section class="section section-mist">
        <div class="container-page">
            <h1 class="type-title">Choose a product to design</h1>
            <p class="type-lead mt-3 max-w-2xl">Every price is shown up front, and it drops as you order more. Sizes and colours count together.</p>

            <div class="mt-8 flex flex-wrap gap-2" role="tablist" aria-label="Product type">
                <a href="{{ route('catalogue.index') }}" @class(['btn', 'btn-brand' => ! $type, 'btn-outline' => $type])>All</a>
                @foreach ($types as $t)
                    <a href="{{ route('catalogue.index', ['type' => $t->value]) }}" @class(['btn', 'btn-brand' => $type === $t, 'btn-outline' => $type !== $t])>{{ $t->plural() }}</a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container-page">
            @if ($products->isEmpty())
                <p class="text-copy">Nothing here yet. Check back soon, or <a class="link-arrow" href="{{ route('catalogue.index') }}">see all products</a>.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($products as $product)
                        @php
                            $single = Money::toMinor($product->singlePrice());
                            $lowest = Money::toMinor($product->tiers->min('unit_price'));
                        @endphp
                        <a href="{{ route('catalogue.show', $product) }}" class="card group block transition hover:-translate-y-0.5 hover:shadow-float">
                            @php($photo = str_starts_with((string) $product->image, 'media/'))
                            <div class="aspect-[4/5] bg-[#e9ecf5]">
                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy"
                                     @class(['size-full', 'object-cover object-top' => $photo, 'object-contain p-8' => ! $photo])>
                            </div>
                            <div class="bg-mist px-6 pb-6 pt-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="type-heading">{{ $product->name }}</h2>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-brand-strong">{{ $product->quality }}</span>
                                </div>
                                <p class="mt-1 text-sm text-copy">{{ $product->type->label() }} · {{ $product->colours->count() }} {{ Str::plural('colour', $product->colours->count()) }}</p>
                                <p class="mt-4 text-sm text-copy">
                                    <span class="text-xl font-extrabold text-ink">{{ Money::format($single) }}</span> each
                                    @if ($lowest < $single)
                                        <span class="text-brand-strong">· down to {{ Money::format($lowest) }} in bulk</span>
                                    @endif
                                </p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
