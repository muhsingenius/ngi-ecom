<x-layouts.guest title="Log in" heading="Welcome back" subheading="Log in to your account.">
    @if (session('status'))
        <p class="mb-4 rounded-control bg-brand-tint px-3 py-2 text-sm text-brand-strong">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-input name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-input name="password" label="Password" type="password" autocomplete="current-password" required />

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-ink">
                <input type="checkbox" name="remember" class="size-4 rounded border-line accent-brand">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-brand-strong hover:underline">Forgot password?</a>
        </div>

        <x-button>Log in</x-button>
    </form>

    <x-slot:footer>
        New here? <a href="{{ route('register') }}" class="font-medium text-brand-strong hover:underline">Create an account</a>
    </x-slot:footer>
</x-layouts.guest>
