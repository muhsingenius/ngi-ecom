<x-layouts.guest title="Sign up" heading="Create your account" subheading="Order custom printed products.">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-input name="name" label="Name" autocomplete="name" required autofocus />
        <x-input name="email" label="Email" type="email" autocomplete="email" required />
        <x-input name="password" label="Password" type="password" autocomplete="new-password" required />
        <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <p class="text-xs leading-relaxed text-muted">
            We use your details to run your account and orders, as explained in our
            <a href="{{ route('privacy') }}" class="underline" target="_blank">Privacy policy</a> and
            <a href="{{ route('data-protection') }}" class="underline" target="_blank">Data protection</a> page.
        </p>

        <x-button>Create account</x-button>
    </form>

    <x-slot:footer>
        Already registered? <a href="{{ route('login') }}" class="font-medium text-brand-strong hover:underline">Log in</a>
    </x-slot:footer>
</x-layouts.guest>
