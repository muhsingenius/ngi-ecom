@php
    use App\Support\Money;
    $canDeliver = $zones->isNotEmpty();
    $canPickup = $pickups->isNotEmpty();
    $default = old('fulfilment', $canDeliver ? 'delivery' : 'pickup');
@endphp

<x-layouts.app title="Checkout">
    <section class="section">
        <div class="container-page">
            <h1 class="type-title">Checkout</h1>

            @if (! $canDeliver && ! $canPickup)
                <div class="mt-8 rounded-card bg-mist p-8 text-copy">Checkout is not available yet because delivery and pickup have not been set up. Please contact the store.</div>
            @else
                <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem]"
                      x-data="{
                          fulfilment: @js($default),
                          zones: @js($zones),
                          zoneId: @js((int) old('zone_id', 0)),
                          subtotal: {{ $quote['subtotal'] }},
                          symbol: @js($symbol),
                          get zone() { return this.zones.find(z => z.id === Number(this.zoneId)) ?? null; },
                          get fee() { return this.fulfilment === 'delivery' && this.zone ? this.zone.fee : 0; },
                          money(minor) { const v = minor / 100; return this.symbol + ' ' + (minor % 100 === 0 ? v.toLocaleString('en-GH') : v.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })); },
                      }">
                    @csrf

                    <div class="space-y-8">
                        @if ($errors->any())
                            <div class="rounded-control bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                                <p class="font-semibold">Please check the form:</p>
                                <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <div class="panel space-y-5 p-6">
                            <h2 class="type-heading">Your details</h2>
                            <x-f.input name="name" label="Full name" :value="$user?->name" autocomplete="name" required />
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-f.input name="phone" label="Phone number" type="tel" autocomplete="tel" inputmode="tel" required help="We call or message this number about your order." />
                                <x-f.input name="email" label="Email" type="email" :value="$user?->email" autocomplete="email" required help="Your order updates and receipt are sent here." />
                            </div>
                        </div>

                        <div class="panel space-y-5 p-6">
                            <h2 class="type-heading">Delivery or pickup</h2>

                            <div class="grid gap-3 sm:grid-cols-2" role="radiogroup">
                                @if ($canDeliver)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-control border p-4" :class="fulfilment === 'delivery' ? 'border-brand bg-brand-tint' : 'border-line'">
                                        <input type="radio" name="fulfilment" value="delivery" x-model="fulfilment" class="accent-brand"> <span class="font-semibold text-ink">Delivery</span>
                                    </label>
                                @endif
                                @if ($canPickup)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-control border p-4" :class="fulfilment === 'pickup' ? 'border-brand bg-brand-tint' : 'border-line'">
                                        <input type="radio" name="fulfilment" value="pickup" x-model="fulfilment" class="accent-brand"> <span class="font-semibold text-ink">Pickup</span>
                                    </label>
                                @endif
                            </div>

                            @if ($canDeliver)
                                <div x-show="fulfilment === 'delivery'" class="space-y-4">
                                    <div>
                                        <label for="zone_id" class="block text-sm font-semibold text-ink">Where are we delivering?</label>
                                        <select id="zone_id" name="zone_id" x-model="zoneId" class="field mt-1.5">
                                            <option value="0" disabled>Choose an area…</option>
                                            <template x-for="z in zones" :key="z.id">
                                                <option :value="z.id" x-text="`${z.name} · ${z.fee === 0 ? 'free' : money(z.fee)}${z.eta ? ' · ' + z.eta : ''}`"></option>
                                            </template>
                                        </select>
                                        <p class="mt-1 text-xs text-muted">Not in one of these areas? Choose pickup, or <a href="{{ route('contact') }}" class="underline">contact the store</a> about delivery to you.</p>
                                    </div>
                                    <p class="text-sm text-copy" x-show="zone && zone.courier" x-cloak>Courier: <span x-text="zone?.courier"></span></p>
                                    <div>
                                        <label for="address" class="block text-sm font-semibold text-ink">Delivery address</label>
                                        <textarea id="address" name="address" rows="3" class="field mt-1.5 py-2" placeholder="House number, street, landmark, town">{{ old('address') }}</textarea>
                                    </div>
                                </div>
                            @endif

                            @if ($canPickup)
                                <div x-show="fulfilment === 'pickup'" x-cloak class="space-y-3">
                                    @foreach ($pickups as $p)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-control border border-line p-4 has-[:checked]:border-brand has-[:checked]:bg-brand-tint">
                                            <input type="radio" name="pickup_point_id" value="{{ $p->id }}" @checked(old('pickup_point_id', $loop->first && ! $canDeliver ? $p->id : null) == $p->id) class="mt-1 accent-brand">
                                            <span><span class="block font-semibold text-ink">{{ $p->name }}</span><span class="block text-sm text-copy">{{ $p->address }}</span>@if ($p->hours)<span class="block text-sm text-muted">{{ $p->hours }}</span>@endif</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="panel p-6">
                            <label for="notes" class="block text-sm font-semibold text-ink">Notes for the store (optional)</label>
                            <textarea id="notes" name="notes" rows="2" maxlength="1000" class="field mt-1.5 py-2">{{ old('notes') }}</textarea>
                        </div>
                    </div>

                    <aside class="h-fit space-y-4 rounded-card bg-mist p-6">
                        <h2 class="type-heading">Order summary</h2>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-copy">Items ({{ $quote['quantity'] }})</dt><dd class="font-semibold text-ink">{{ Money::format($quote['subtotal']) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-copy">Delivery</dt><dd class="font-semibold text-ink" x-text="fulfilment === 'pickup' ? 'Free (pickup)' : (zone ? (fee === 0 ? 'Free' : money(fee)) : 'Choose an area')"></dd></div>
                            <div class="flex justify-between border-t border-line pt-3 text-base"><dt class="font-semibold text-ink">Total</dt><dd class="font-extrabold text-ink" x-text="money(subtotal + fee)"></dd></div>
                        </dl>

                        @if ($turnaround !== null)<p class="text-sm text-copy">Usually ready in about {{ $turnaround }} {{ \Illuminate\Support\Str::plural('day', $turnaround) }} after payment{{ $proofRequired ? ' and proof approval' : '' }}.</p>@endif

                        @if ($proofRequired)
                            <p class="rounded-control bg-white p-3 text-sm text-copy">
                                An order this size gets a <span class="font-semibold text-ink">proof</span> to approve before printing.
                                {{ $proofTiming === 'proof_then_pay' ? 'You will approve the proof first, then pay.' : 'You pay now, then approve the proof.' }}
                            </p>
                        @endif

                        <button class="btn btn-brand btn-lg btn-block">{{ $proofRequired && $proofTiming === 'proof_then_pay' ? 'Place order' : 'Place order and pay' }}</button>
                        <p class="text-xs text-muted">Pay by card or Mobile Money through Paystack.</p>
                        <a href="{{ route('cart.index') }}" class="link-arrow justify-center text-sm">Back to cart</a>
                    </aside>
                </form>
            @endif
        </div>
    </section>
</x-layouts.app>
