@php use App\Support\Money; @endphp

<x-layouts.app title="Contact">
    <section class="section">
        <div class="container-page max-w-4xl">
            <h1 class="type-title">Contact the store</h1>
            <p class="type-lead mt-3 max-w-2xl">Questions about a design, a bulk order or delivery? Reach us any of these ways and quote your order number if you have one.</p>

            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                @if ($phone)
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}" class="panel block p-5 transition hover:shadow-float"><p class="text-sm text-muted">Call</p><p class="mt-1 font-bold text-ink">{{ $phone }}</p></a>
                @endif
                @if ($whatsapp)
                    <a href="https://wa.me/{{ ltrim($whatsapp, '+') }}" class="panel block p-5 transition hover:shadow-float" rel="noopener"><p class="text-sm text-muted">WhatsApp</p><p class="mt-1 font-bold text-ink">Chat with us</p></a>
                @endif
                @if ($email)
                    <a href="mailto:{{ $email }}" class="panel block p-5 transition hover:shadow-float"><p class="text-sm text-muted">Email</p><p class="mt-1 break-all font-bold text-ink">{{ $email }}</p></a>
                @endif
            </div>

            @unless ($phone || $whatsapp || $email)
                <p class="mt-8 rounded-card bg-mist p-6 text-copy">The store's contact details are being set up. Please check back soon.</p>
            @endunless

            @if ($pickups->isNotEmpty())
                <h2 class="type-heading mt-12">Pickup points</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($pickups as $p)
                        <li class="panel p-5 text-sm"><p class="font-semibold text-ink">{{ $p->name }}</p><p class="whitespace-pre-line text-copy">{{ $p->address }}</p>@if ($p->hours)<p class="text-muted">{{ $p->hours }}</p>@endif</li>
                    @endforeach
                </ul>
            @endif

            @if ($zones->isNotEmpty())
                <h2 class="type-heading mt-12">Delivery</h2>
                <p class="mt-2 text-sm text-copy">We deliver to these areas. Not on the list? Contact us or choose pickup.</p>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    @foreach ($zones as $z)
                        <li class="panel p-4 text-sm"><p class="font-semibold text-ink">{{ $z->name }}</p><p class="text-copy">{{ $z->fee_type === 'flat' ? Money::format(Money::toMinor($z->flat_fee)) : 'Fee depends on the number of items' }}@if ($z->eta) · {{ $z->eta }}@endif</p></li>
                    @endforeach
                </ul>
            @endif

            @if ($turnaround !== null)<p class="mt-10 text-sm text-copy">Orders are usually ready in about {{ $turnaround }} {{ \Illuminate\Support\Str::plural('day', $turnaround) }} after payment (and proof approval, where a proof applies).</p>@endif
        </div>
    </section>
</x-layouts.app>
