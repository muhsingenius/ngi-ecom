<x-layouts.admin heading="Settings">
    <p class="type-lead max-w-2xl">Every setting has a sensible default, so the store works from day one. Changes apply immediately.</p>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="panel mt-6 max-w-2xl space-y-6 p-6">
        @csrf @method('PUT')

        @foreach ($definitions as $key => $definition)
            @php($field = str_replace('.', '_', $key))
            @if (isset($definition['options']))
                <x-f.select :name="$field" :label="$definition['label']" :options="$definition['options']" :value="$values[$key]" :help="$definition['help']" />
            @elseif ($key === 'shop.notification_email')
                <x-f.input :name="$field" :label="$definition['label']" type="email" :value="$values[$key]" :help="$definition['help']" />
            @else
                <x-f.input :name="$field" :label="$definition['label']" type="number" step="{{ $definition['type'] === 'float' ? '0.01' : '1' }}" min="0" :value="$values[$key]" :help="$definition['help']" />
            @endif
        @endforeach

        <button class="btn btn-brand">Save settings</button>
    </form>
</x-layouts.admin>
