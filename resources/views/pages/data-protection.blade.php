@php
    $org = config('shop.organisation');
    $name = config('app.name');
@endphp

<x-layouts.app title="Data protection">
    <section class="section">
        <div class="container-page">
            <h1 class="type-title">Data protection</h1>
            <p class="mt-2 text-sm text-muted">Last updated {{ $updated }}</p>

            <div class="legal mt-6">
                <p>
                    {{ $name }} is run by the {{ $org['name'] }}, an organisation that works with women and girls, many of them young. We take that responsibility seriously.
                    This page explains how we protect personal data and how you can exercise your rights under Ghana's Data Protection Act, 2012 (Act 843).
                    For what we collect and why, read the <a href="{{ route('privacy') }}">Privacy policy</a>.
                </p>

                <h2>How we protect your data</h2>
                <ul>
                    <li><strong>Private file storage.</strong> Uploaded images and print files are stored in a private area that has no public web address. They can be opened only through signed links that expire.</li>
                    <li><strong>Checked uploads.</strong> Every upload is checked by its content, images are re-encoded and graphics are cleaned of anything that could run code.</li>
                    <li><strong>Payments stay with Paystack.</strong> We never see or store card numbers or Mobile Money PINs. We treat an order as paid only when Paystack confirms it to us with a verified signature and the amount and currency match.</li>
                    <li><strong>Passwords are never stored in plain text.</strong> Sign-in attempts and other sensitive actions are rate limited.</li>
                    <li><strong>Access by role.</strong> Customers see only their own orders. The print shop sees only the jobs it must produce, without prices or payment details. Administration is limited to authorised staff.</li>
                    <li><strong>Private links.</strong> Order and proof pages for guests use long random links that cannot be guessed.</li>
                    <li><strong>Secure connections and backups.</strong> The site is served over HTTPS, and the files and database are backed up daily by our hosting.</li>
                    <li><strong>Tidy-up.</strong> Unused guest designs and uploads are deleted automatically after 30 days.</li>
                </ul>

                <h2>Who is responsible</h2>
                <p>{{ $org['name'] }}, {{ $org['address'] }}, is the organisation responsible for the personal data collected through {{ $name }}.</p>
                <table>
                    <thead><tr><th>Who</th><th>Role</th><th>What they receive</th></tr></thead>
                    <tbody>
                        <tr><td>Print shop</td><td>Produces your order</td><td>Name, phone, delivery or pickup details, order contents, design files</td></tr>
                        <tr><td>Couriers and riders</td><td>Deliver your order</td><td>Name, phone, delivery address</td></tr>
                        <tr><td>Paystack</td><td>Processes payments</td><td>Name, email, phone, amount; payment details you enter on its page</td></tr>
                        <tr><td>Hosting and email providers</td><td>Run the website and send emails</td><td>The information needed to provide those services</td></tr>
                    </tbody>
                </table>

                <h2>Your rights</h2>
                <ul>
                    <li><strong>Access:</strong> ask what personal data we hold about you and how we use it.</li>
                    <li><strong>Correction:</strong> ask us to fix data that is wrong or incomplete.</li>
                    <li><strong>Objection and restriction:</strong> ask us to stop or limit how we use your data.</li>
                    <li><strong>Deletion:</strong> ask us to delete your data, unless we must keep it by law (for example order and payment records).</li>
                    <li><strong>Withdrawing consent:</strong> where we rely on your consent, you can withdraw it at any time.</li>
                </ul>

                <h3>How to make a request</h3>
                <p>
                    Contact us
                    @if ($email) at <a href="mailto:{{ $email }}">{{ $email }}</a>@endif
                    @if ($email && $phone) or @endif
                    @if ($phone) on <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@endif
                    @unless ($email || $phone) through our <a href="{{ route('contact') }}">contact page</a>@endunless
                    and tell us what you would like. Include your order number if you have one. We may ask you to confirm who you are before we share or change anything, so that nobody else can obtain your data. We will reply as soon as we can and within the time the law allows.
                </p>

                <h3>If you are not satisfied</h3>
                <p>You can complain to the Data Protection Commission of Ghana, the regulator for Act 843.</p>

                <h2>If something goes wrong</h2>
                <p>If a security incident is likely to affect your personal data, we will act quickly to contain it and tell the people affected and the Data Protection Commission, as the law requires.</p>

                <h2>Names and numbers of other people</h2>
                <p>Groups often enter the names of members. If you enter other people's names, please make sure they know and agree. We use those names only to print the order and to show them to you for checking on your proof.</p>
            </div>
        </div>
    </section>
</x-layouts.app>
