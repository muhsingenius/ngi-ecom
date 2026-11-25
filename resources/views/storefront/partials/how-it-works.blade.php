{{-- A real sequence, so the numbering carries meaning here. --}}
<section id="how-it-works" class="section section-mist">
    <div class="container-page">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="type-title">How ordering works</h2>
            <p class="type-lead mt-4">Four steps from idea to doorstep.</p>
        </div>

        <ol class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['box', 'Choose a product', 'Pick the item, size and colour.'],
                ['pencil', 'Add your design', 'Upload artwork or add text in the designer.'],
                ['eye', 'Check the proof', 'Review how it will print and ask for changes.'],
                ['truck', 'We print and deliver', 'Your order is printed and sent to you.'],
            ] as $i => [$icon, $title, $text])
                <li class="panel relative px-6 pb-8 pt-9 text-center">
                    <span class="absolute right-5 top-4 text-sm font-extrabold text-brand">{{ $i + 1 }}</span>
                    <span class="well-icon mx-auto"><x-icon :name="$icon" class="size-7" /></span>
                    <h3 class="type-heading mt-6">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-copy">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
