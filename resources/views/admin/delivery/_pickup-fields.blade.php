@php($v = fn ($key, $default = null) => $pickup?->{$key} ?? $default)

<div class="space-y-5">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-f.input name="name" label="Name" :value="$v('name')" placeholder="Our shop" required />
        <x-f.input name="hours" label="Opening hours" :value="$v('hours')" placeholder="Mon-Sat, 9am-6pm" />
    </div>
    <div>
        <label for="address" class="block text-sm font-semibold text-ink">Address</label>
        <textarea id="address" name="address" rows="2" class="field mt-1.5 py-2" required>{{ old('address', $v('address')) }}</textarea>
    </div>
    <x-f.check name="active" label="Available to customers" :checked="$v('active', true)" />
</div>
