@php use App\Support\Money; @endphp

<x-layouts.admin heading="Products">
    <div class="flex justify-end">
        <a href="{{ route('admin.products.create') }}" class="btn btn-brand">Add product</a>
    </div>

    <div class="mt-6 overflow-x-auto rounded-card border border-line">
        <table class="w-full min-w-[40rem] text-left text-sm">
            <thead class="bg-mist text-muted">
                <tr>
                    <th class="px-4 py-3 font-semibold">Product</th>
                    <th class="px-4 py-3 font-semibold">Quality</th>
                    <th class="px-4 py-3 font-semibold">From → bulk</th>
                    <th class="px-4 py-3 font-semibold">Colours</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    <tr class="border-t border-line">
                        <td class="px-4 py-3 font-semibold text-ink">{{ $product->name }} <span class="font-normal text-muted">· {{ $product->type->label() }}</span></td>
                        <td class="px-4 py-3">{{ $product->quality }}</td>
                        <td class="px-4 py-3">{{ Money::format(Money::toMinor($product->singlePrice())) }} → {{ Money::format(Money::toMinor($product->tiers->min('unit_price'))) }}</td>
                        <td class="px-4 py-3">{{ $product->colours->count() }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-green-50 text-green-700' => $product->active, 'bg-mist text-muted' => ! $product->active])>{{ $product->active ? 'Live' : 'Hidden' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.products.edit', $product) }}" class="link-arrow">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-copy">No products yet. Add your first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
