@php
    $org = config('shop.organisation');
@endphp

<x-layouts.app title="About">
    <section class="section section-mist">
        <div class="container-page">
            <span class="pill"><x-icon name="star" class="size-4" /> A social enterprise</span>
            <h1 class="type-display mt-6 max-w-3xl">Printing with a purpose</h1>
            <p class="type-lead mt-6 max-w-2xl">
                {{ config('app.name') }} makes custom printed T-shirts, polos, hoodies, caps and bracelets for individuals, schools, churches, teams and companies.
                It is the social enterprise of the <a href="{{ $org['url'] }}" class="font-semibold text-brand-strong underline" target="_blank" rel="noopener">{{ $org['name'] }}</a> ({{ $org['short'] }}), a women-led non-profit in northern Ghana.
            </p>
        </div>
    </section>

    <section class="section">
        <div class="container-page legal !max-w-5xl">
            <div class="grid gap-12 lg:grid-cols-2">
                <div>
                    <h2 class="!mt-0">What we do</h2>
                    <p>You design your product in the browser, see the price before you pay, and we have it printed by a local print shop and delivered to you or ready for pickup.</p>
                    <ul>
                        <li><strong>Individuals</strong> get a quick, friendly designer that works on a phone.</li>
                        <li><strong>Groups</strong> get bulk prices that drop as the quantity rises, a name and number on every item, and a proof to approve before anything is printed.</li>
                        <li><strong>Everyone</strong> pays by card or Mobile Money, in Ghana cedis.</li>
                    </ul>
                </div>

                <div>
                    <h2 class="!mt-0">Why a social enterprise</h2>
                    <p>A social enterprise is a business with a purpose. {{ config('app.name') }} exists to serve the mission of {{ $org['name'] }}: helping women and girls in northern Ghana to learn, earn and lead.</p>
                    <p>When you order from us you are buying a good product from a business that was set up for a reason bigger than the sale.</p>
                </div>
            </div>

            <h2>About {{ $org['name'] }}</h2>
            <p>
                {{ $org['name'] }} is a non-profit organisation registered in Canada and Ghana. It was founded in 2016 to promote the social and economic well-being of women and girls in northern Ghana,
                by providing opportunities for education, skills development, leadership and economic participation.
            </p>
            <p>
                NGI works mainly in underserved communities where women and girls have limited access to education, economic opportunities and decision-making spaces.
                It operates in six districts (Savelugu Municipal, Nanton, Kumbungu, Tolon, Tamale Metro and Sagnarigu Municipal), and its main beneficiaries are marginalised women and girls, mostly between the ages of 12 and 35.
            </p>

            <div class="mt-8 grid gap-6 sm:grid-cols-2">
                <div class="rounded-card bg-mist p-6">
                    <h3 class="!mt-0">Vision</h3>
                    <p class="!mt-2">A society where women and girls in northern Ghana are empowered, self-reliant, and actively contributing to sustainable community development.</p>
                </div>
                <div class="rounded-card bg-mist p-6">
                    <h3 class="!mt-0">Mission</h3>
                    <p class="!mt-2">To foster the growth of women and girls through education, economic empowerment, health awareness, digital literacy, mentorship, and community engagement.</p>
                </div>
            </div>

            <h3>What NGI works on</h3>
            <ul>
                <li>Education and skills development</li>
                <li>Digital inclusion and innovation</li>
                <li>Climate action and environmental sustainability</li>
                <li>Enterprise and economic empowerment</li>
                <li>Health, wellbeing and social inclusion</li>
            </ul>

            <h3>NGI in numbers</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach ([['500+', 'students received ICT lessons through the Innovation Hub project'], ['350', 'girls reached through the Read, Rise & Thrive English literacy bootcamps'], ['200', 'young women trained in coding and digital skills']] as [$figure, $label])
                    <div class="rounded-card border border-line p-5"><p class="text-3xl font-extrabold tracking-tight text-brand-strong">{{ $figure }}</p><p class="mt-1 text-sm">{{ $label }}</p></div>
                @endforeach
            </div>
            <p class="text-sm">More than 100 youth volunteers have supported NGI's projects, and 80% of them are women. Figures are as published on <a href="{{ $org['url'] }}" target="_blank" rel="noopener">{{ parse_url($org['url'], PHP_URL_HOST) }}</a>.</p>

            <h3>Values</h3>
            <p>Inclusivity, integrity, collaboration, sustainability and commitment to volunteers.</p>

            <h2>Learn more or get involved</h2>
            <p>To read about NGI's programmes, news and team, or to support its work directly, visit the NGI website.</p>
            <div class="mt-6 flex flex-wrap gap-3 not-prose">
                <a href="{{ $org['url'] }}" class="btn btn-brand !no-underline !text-white" target="_blank" rel="noopener">Visit {{ $org['short'] }}</a>
                <a href="{{ $org['donate_url'] }}" class="btn btn-outline !no-underline !text-ink" target="_blank" rel="noopener">Donate to {{ $org['short'] }}</a>
                <a href="{{ route('contact') }}" class="btn btn-outline !no-underline !text-ink">Contact {{ config('app.name') }}</a>
            </div>
            <p class="text-sm">{{ $org['name'] }}, {{ $org['address'] }}.</p>
        </div>
    </section>
</x-layouts.app>
