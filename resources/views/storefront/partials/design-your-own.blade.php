<section id="design" class="section">
    <div class="container-page grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
        <div>
            <span class="pill bg-brand-tint shadow-none"><x-icon name="pencil" class="size-4" /> Design studio</span>

            <h2 class="type-title mt-5">Start from a blank product</h2>
            <p class="type-lead mt-4 max-w-xl">
                Drop in your artwork, add a line of text and pick your colours. You decide where everything goes.
            </p>

            <ul class="mt-8 space-y-5">
                @foreach ([
                    ['upload', 'Upload your artwork', 'Use your own logo, photo or illustration.'],
                    ['type', 'Add your text', 'Names, slogans or dates in the style you like.'],
                    ['eye', 'Preview the result', 'See how it will look before it goes to print.'],
                ] as [$icon, $title, $text])
                    <li class="flex items-start gap-4">
                        <span class="chip-icon"><x-icon :name="$icon" /></span>
                        <div>
                            <h3 class="font-bold text-ink">{{ $title }}</h3>
                            <p class="text-sm text-copy">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>

            <a href="#how-it-works" class="link-arrow mt-8">See how ordering works <x-icon name="arrow" class="size-4" /></a>
        </div>

        <div class="relative mx-auto w-full max-w-md lg:max-w-none">
            <div class="absolute -right-5 -top-5 hidden size-40 rounded-full bg-accent-tint sm:block"></div>

            <div class="relative overflow-hidden rounded-[1.25rem] bg-white shadow-float ring-1 ring-line">
                <div class="bg-mist px-10 pb-4 pt-10">
                    <x-product-art name="mug" class="mx-auto h-72 w-full" />
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm font-semibold text-brand-strong">Your design</p>
                    <h3 class="type-heading mt-1 text-xl">Custom mug</h3>
                </div>
            </div>

            <div class="float-card absolute -left-2 top-6 hidden sm:flex">
                <span class="chip-icon"><x-icon name="pencil" class="size-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink">Edit design</p>
                    <p class="text-xs text-muted">Change anything</p>
                </div>
            </div>

            <div class="float-card absolute -right-2 bottom-8 hidden sm:flex">
                <span class="chip-icon"><x-icon name="check" class="size-5" /></span>
                <div>
                    <p class="text-sm font-bold text-ink">Ready to print</p>
                    <p class="text-xs text-muted">After your approval</p>
                </div>
            </div>
        </div>
    </div>
</section>
