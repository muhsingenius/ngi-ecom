<section class="section section-mist overflow-hidden">
    <div class="container-page grid items-center gap-8 lg:grid-cols-[1.05fr_1fr] lg:gap-8">
        <div class="text-center lg:text-left">
            <span class="pill"><x-icon name="pencil" class="size-4" /> Made-to-order printing</span>

            <h1 class="type-display mt-6">Your design, printed on things worth keeping</h1>

            <p class="type-lead mx-auto mt-6 max-w-xl lg:mx-0">
                Design it yourself in minutes, or order in bulk for your school, church, team or company. See the price before you pay, and see the proof before we print.
            </p>

            <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center sm:gap-6 lg:justify-start">
                <a href="{{ route('catalogue.index') }}" class="btn btn-brand btn-lg"><x-icon name="pencil" class="size-4" /> Design your own</a>
                <a href="#products" class="link-arrow justify-center">Browse products <x-icon name="arrow" class="size-4" /></a>
            </div>

            <ul class="mx-auto mt-10 flex w-fit flex-col items-start gap-4 sm:w-auto sm:flex-row sm:items-center sm:justify-center sm:gap-8 lg:mx-0 lg:justify-start">
                @foreach ([['pencil', 'Easy online design'], ['star', 'Bulk pricing that drops'], ['truck', 'Delivery or pickup']] as [$icon, $label])
                    <li class="flex items-center gap-3 text-sm font-semibold text-ink">
                        <span class="chip-icon"><x-icon :name="$icon" class="size-5" /></span>
                        {{ $label }}
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Photo with a tinted circle behind it, floating cards and a badge --}}
        <div class="relative order-first mx-auto w-full max-w-[26rem] lg:order-none lg:max-w-[28rem]">
            <div class="absolute -right-6 -top-6 size-[70%] rounded-full bg-brand-tint"></div>
            <div class="absolute -bottom-5 -left-5 size-[38%] rounded-full bg-accent-tint"></div>

            <picture>
                <source type="image/webp" media="(max-width: 640px)" srcset="{{ asset('media/hero-560.webp') }}">
                <img src="{{ asset('media/hero-1000.webp') }}" width="1000" height="1339" fetchpriority="high"
                     alt="A customer wearing a grey custom-printed T-shirt"
                     class="relative aspect-[4/3] w-full rounded-[1.5rem] sm:aspect-[4/5] object-cover object-top shadow-float">
            </picture>

            <div class="float-card settle absolute -left-3 top-[22%] sm:-left-8" style="--settle-delay: .25s">
                <span class="chip-icon"><x-icon name="upload" class="size-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink">Your artwork</p>
                    <p class="text-xs text-muted">Placed your way</p>
                </div>
            </div>

            <div class="float-card settle absolute -right-3 bottom-[14%] sm:-right-8" style="--settle-delay: .45s">
                <span class="chip-icon"><x-icon name="check" class="size-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink">Proof first</p>
                    <p class="text-xs text-muted">Approve before we print</p>
                </div>
            </div>

            <div class="settle absolute -right-2 top-4 flex size-24 flex-col items-center justify-center rounded-full bg-brand text-center text-white shadow-brand sm:-right-6 sm:size-28" style="--settle-delay: .65s">
                <span class="text-sm font-extrabold leading-tight">Made<br>to order</span>
            </div>
        </div>
    </div>
</section>
