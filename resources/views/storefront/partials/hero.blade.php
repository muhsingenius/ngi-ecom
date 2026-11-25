<section class="section section-mist overflow-hidden">
    <div class="container-page grid items-center gap-12 lg:grid-cols-[1.05fr_1fr] lg:gap-8">
        <div class="text-center lg:text-left">
            <span class="pill"><x-icon name="pencil" class="size-4" /> Made-to-order printing</span>

            <h1 class="type-display mt-6">Your design, printed on things worth keeping</h1>

            <p class="type-lead mx-auto mt-6 max-w-xl lg:mx-0">
                Upload your artwork, add your own text and see how it looks on the product before you order. We print it and deliver it to you.
            </p>

            <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center sm:gap-6 lg:justify-start">
                <a href="#how-it-works" class="btn btn-brand btn-lg"><x-icon name="pencil" class="size-4" /> Design your own</a>
                <a href="#products" class="link-arrow justify-center">Browse products <x-icon name="arrow" class="size-4" /></a>
            </div>

            <ul class="mx-auto mt-10 flex w-fit flex-col items-start gap-4 sm:w-auto sm:flex-row sm:items-center sm:justify-center sm:gap-8 lg:mx-0 lg:justify-start">
                @foreach ([['pencil', 'Easy online design'], ['star', 'Quality printing'], ['truck', 'Delivered to you']] as [$icon, $label])
                    <li class="flex items-center gap-3 text-sm font-semibold text-ink">
                        <span class="chip-icon"><x-icon :name="$icon" class="size-5" /></span>
                        {{ $label }}
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Product composition: tinted circle, dashed ring, floating cards --}}
        <div class="relative mx-auto aspect-square w-full max-w-[32rem]">
            <div class="absolute inset-[4%] rounded-full bg-brand-tint"></div>
            <div class="absolute inset-[12%] rounded-full border-2 border-dashed border-brand/25"></div>

            <x-product-art name="tshirt" class="absolute inset-[18%] size-[64%] drop-shadow-[0_20px_30px_rgb(18_20_28/0.18)]" />

            <div class="float-card settle absolute left-0 top-[24%] sm:-left-2" style="--settle-delay: .25s">
                <span class="chip-icon"><x-icon name="upload" class="size-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink">Your artwork</p>
                    <p class="text-xs text-muted">Placed your way</p>
                </div>
            </div>

            <div class="float-card settle absolute bottom-[12%] right-0 sm:-right-2" style="--settle-delay: .45s">
                <span class="chip-icon"><x-icon name="check" class="size-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink">Proof first</p>
                    <p class="text-xs text-muted">Approve before we print</p>
                </div>
            </div>

            <div class="settle absolute right-[2%] top-[6%] flex size-24 flex-col items-center justify-center rounded-full bg-brand text-center text-white shadow-brand sm:size-28" style="--settle-delay: .65s">
                <span class="text-sm font-extrabold leading-tight">Made<br>to order</span>
            </div>
        </div>
    </div>
</section>
