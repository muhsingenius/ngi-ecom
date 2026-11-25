<section id="design" class="section">
    <div class="container-page grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
        <div>
            <span class="pill bg-brand-tint shadow-none"><x-icon name="pencil" class="size-4" /> Design studio</span>

            <h2 class="type-title mt-5">Start from a blank product</h2>
            <p class="type-lead mt-4 max-w-xl">
                Drop in your artwork, add a line of text and pick your colours. You decide where everything goes, front and back.
            </p>

            <ul class="mt-8 space-y-5">
                @foreach ([
                    ['upload', 'Upload your artwork', 'Use your own logo, photo or illustration.'],
                    ['type', 'Add your text', 'Names, slogans or numbers in the style you like.'],
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

            <a href="#how-it-works" class="btn btn-brand btn-lg mt-8">See how ordering works <x-icon name="arrow" class="size-4" /></a>
        </div>

        {{-- Demo video, played at 1.5x --}}
        <div class="relative mx-auto w-full max-w-md lg:max-w-none"
             x-data="{ playing: false, reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches }"
             x-init="
                const v = $refs.video;
                const fast = () => { v.playbackRate = 1.5; };
                v.addEventListener('loadedmetadata', fast);
                v.addEventListener('play', fast);
                v.addEventListener('play', () => playing = true);
                v.addEventListener('pause', () => playing = false);
                fast();
                if (! reduced) { v.play().catch(() => {}); }
             ">
            <div class="absolute -right-5 -top-5 hidden size-40 rounded-full bg-accent-tint sm:block"></div>

            <div class="relative overflow-hidden rounded-[1.25rem] bg-night shadow-float ring-1 ring-line">
                <video x-ref="video" class="block w-full" muted loop playsinline preload="auto"
                       aria-label="Demo of the design studio">
                    <source src="{{ asset('media/video_demo.mp4') }}" type="video/mp4">
                </video>

                <button type="button" x-on:click="playing ? $refs.video.pause() : $refs.video.play()"
                        class="absolute bottom-4 right-4 inline-flex size-11 items-center justify-center rounded-full bg-white/90 text-ink shadow-float hover:bg-white"
                        x-bind:aria-label="playing ? 'Pause demo' : 'Play demo'">
                    <svg x-show="! playing" class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                    <svg x-show="playing" x-cloak class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5h4v14H7zM13 5h4v14h-4z" /></svg>
                </button>
            </div>
        </div>
    </div>
</section>
