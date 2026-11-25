<x-layouts.guest title="Forgot password" heading="Forgot your password?" subheading="Enter your email and we'll send you a reset link.">
    @if (session('status'))
        <p class="mb-4 rounded-control bg-brand-tint px-3 py-2 text-sm text-brand-strong">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-input name="email" label="Email" type="email" autocomplete="email" required autofocus />

        <x-button>Email reset link</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="font-medium text-brand-strong hover:underline">Back to log in</a>
    </x-slot:footer>
</x-layouts.guest>
