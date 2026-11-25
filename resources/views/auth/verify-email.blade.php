<x-layouts.guest title="Verify email" heading="Verify your email" subheading="We sent a verification link to your email address. Click it to finish setting up your account.">
    @if (session('status') === 'verification-link-sent')
        <p class="mb-4 rounded-control bg-brand-tint px-3 py-2 text-sm text-brand-strong">A new verification link has been sent to your email address.</p>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="space-y-3">
        @csrf
        <x-button>Resend verification email</x-button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button class="w-full rounded-control px-4 py-2.5 text-sm font-medium text-copy hover:bg-mist">Log out</button>
    </form>
</x-layouts.guest>
