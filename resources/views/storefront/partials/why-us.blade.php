@inject('settings', 'App\Services\Settings')

<section class="section section-night">
    <div class="container-page">
        <div class="mx-auto max-w-2xl text-center">
            <span class="pill"><x-icon name="shield" class="size-4" /> Why order from us</span>
            <h2 class="type-title mt-5">Printing that's simple, quick and dependable</h2>
            <p class="type-lead mt-4">From ready-made designs to ones you build yourself, ordering stays easy and you stay in control.</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['pencil', 'Easy customisation', 'Add your own text, images and logos in a simple online designer.'],
                ['star', 'Quality printing', 'Clean artwork and careful finishing so every product looks right.'],
                ['bolt', 'Quick turnaround', 'Most orders are produced within '.$settings->int('orders.turnaround_days').' days.'],
                ['check', 'Proof before print', 'Review how your design will print and request changes first.'],
            ] as [$icon, $title, $text])
                <div class="panel-night p-7">
                    <span class="chip-icon size-12"><x-icon :name="$icon" class="size-6" /></span>
                    <h3 class="type-heading mt-6">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-[#9ca8b8]">{{ $text }}</p>
                </div>
            @endforeach
        </div>

        @guest
            <div class="mt-12 flex flex-col items-start justify-between gap-6 rounded-card border border-white/10 bg-gradient-to-r from-white/[0.06] to-accent/10 p-8 md:flex-row md:items-center md:p-10">
                <div>
                    <h3 class="text-2xl font-extrabold tracking-tight text-white">Ready to make something of your own?</h3>
                    <p class="mt-2 text-[#aeb7c5]">Create an account to save your designs and follow your orders.</p>
                </div>
                <a href="{{ route('register') }}" class="btn btn-accent btn-lg shrink-0">Create account <x-icon name="arrow" class="size-4" /></a>
            </div>
        @endguest
    </div>
</section>
