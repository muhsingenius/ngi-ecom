@php
    $org = config('shop.organisation');
    $name = config('app.name');
@endphp

<x-layouts.app title="Privacy policy">
    <section class="section">
        <div class="container-page">
            <h1 class="type-title">Privacy policy</h1>
            <p class="mt-2 text-sm text-muted">Last updated {{ $updated }}</p>

            <div class="legal mt-6">
                <p>
                    This policy explains what personal information {{ $name }} collects when you design, order or create an account, why we need it, who we share it with and what choices you have.
                    {{ $name }} is the social enterprise of the {{ $org['name'] }} and is operated by it ({{ $org['address'] }}), which is the data controller for the information described here.
                    We handle personal data in line with Ghana's Data Protection Act, 2012 (Act 843). Our <a href="{{ route('data-protection') }}">Data protection</a> page describes how we keep it safe and how to exercise your rights.
                </p>

                <h2>What we collect</h2>
                <ul>
                    <li><strong>Account details:</strong> your name, email address and password (stored only as a one-way hash).</li>
                    <li><strong>Order details:</strong> name, phone number, email, delivery address or chosen pickup point, what you ordered, sizes, colours, and any notes you add.</li>
                    <li><strong>Names and numbers for groups:</strong> the names and numbers you enter to be printed. If you enter other people's names you should have their agreement.</li>
                    <li><strong>Your designs and uploads:</strong> the text, images and layouts you create, and the original files you upload.</li>
                    <li><strong>Payment information:</strong> payments are made on Paystack's page. We receive a reference, the amount, the status and the payment channel (for example card or Mobile Money). We never see or store your card number or Mobile Money PIN.</li>
                    <li><strong>Messages:</strong> emails and notes you send us, and the order emails we send you.</li>
                    <li><strong>Technical information:</strong> a session cookie and a security token that keep you signed in and protect forms, and your browser's own storage for design autosave (see "Cookies and browser storage").</li>
                </ul>

                <h2>Why we use it</h2>
                <table>
                    <thead><tr><th>Purpose</th><th>Basis</th></tr></thead>
                    <tbody>
                        <tr><td>Taking, printing, delivering and supporting your order; sending proofs and order updates</td><td>To carry out your order (contract)</td></tr>
                        <tr><td>Keeping order and payment records, accounting and tax</td><td>Legal obligations</td></tr>
                        <tr><td>Protecting the store from fraud and abuse, and keeping it secure</td><td>Our legitimate interests</td></tr>
                        <tr><td>Keeping your saved designs and order history in your account</td><td>Your request (consent)</td></tr>
                    </tbody>
                </table>
                <p>We do not use your information for advertising, we do not sell it, and we do not send marketing emails.</p>

                <h2>Who we share it with</h2>
                <ul>
                    <li><strong>The print shop</strong> that produces your order sees your name, phone number, delivery or pickup details, the order contents and your design files. It does not see prices or payment details.</li>
                    <li><strong>Couriers and riders</strong> who deliver your order see your name, phone number and delivery address.</li>
                    <li><strong>Paystack</strong> processes your payment and handles the payment details you give it under its own privacy policy.</li>
                    <li><strong>Service providers</strong> who host this website and send our emails, only so that they can provide those services to us.</li>
                    <li><strong>{{ $org['name'] }}</strong>, which operates {{ $name }}.</li>
                    <li><strong>Authorities</strong>, when the law requires it.</li>
                </ul>
                <p>Some of these providers may process information outside Ghana. We expect them to protect it to a standard comparable to Ghana's data protection law.</p>

                <h2>How long we keep it</h2>
                <ul>
                    <li>Designs and uploads made as a guest that are not in a cart or an order are deleted automatically after 30 days.</li>
                    <li>Files that belong to an order are kept with the order, so the print shop can produce it and we can help you with questions or reorders.</li>
                    <li>Order and payment records are kept for as long as we need them for accounting, legal and support reasons.</li>
                    <li>Your account and saved designs are kept until you ask us to delete them.</li>
                </ul>

                <h2>Cookies and browser storage</h2>
                <p>We use only what the site needs to work: a session cookie (so you stay signed in and your cart is kept), a security token that protects forms, and your browser's local storage so the designer can restore your unsaved work on the same device. We do not use advertising or analytics trackers.</p>
                <p>The designer's fonts are loaded from Bunny Fonts (fonts.bunny.net), so your browser contacts that service when you open the designer.</p>

                <h2>Your choices and rights</h2>
                <p>Under Act 843 you can ask us what we hold about you, ask us to correct it, object to or restrict how we use it, withdraw a consent you gave, and ask us to delete it where we have no legal reason to keep it. See <a href="{{ route('data-protection') }}">Data protection</a> for how to do this and how to complain to the Data Protection Commission.</p>

                <h2>Young people</h2>
                <p>If you are under 18, please ask a parent or guardian to place the order and to create any account. We do not knowingly collect personal information from children without a parent or guardian being involved.</p>

                <h2>Changes</h2>
                <p>If we change this policy we will update the date above, and tell account holders by email when the change matters.</p>

                <h2>Contact us</h2>
                <p>
                    Questions about your information: @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endif
                    @if ($email && $phone) or @endif
                    @if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@endif
                    @unless ($email || $phone) use our <a href="{{ route('contact') }}">contact page</a>@endunless.
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
