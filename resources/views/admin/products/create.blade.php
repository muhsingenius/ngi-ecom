<x-layouts.admin heading="Add product">
    <form method="POST" action="{{ route('admin.products.store') }}" class="panel max-w-xl space-y-5 p-6">
        @csrf
        <x-f.select name="type" label="Product type" :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
        <x-f.input name="name" label="Name" placeholder="Classic T-shirt" required />
        <x-f.input name="quality" label="Quality" value="Standard" help="The same product can exist in several qualities, each with its own prices." required />
        <x-f.input name="price" label="Single-item price" type="number" step="0.01" min="0" required help="You can add bulk tiers after creating the product." />
        <button class="btn btn-brand">Create product</button>
    </form>
</x-layouts.admin>
