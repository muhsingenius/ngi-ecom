<x-layouts.guest title="Reset password" heading="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium text-stone-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required readonly
                   class="mt-1 block w-full rounded-lg border border-stone-300 bg-stone-50 px-3 py-2.5 text-base text-stone-600">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-input name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        <x-button>Reset password</x-button>
    </form>
</x-layouts.guest>
