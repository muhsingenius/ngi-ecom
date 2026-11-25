<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Enums\TierScope;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('admin.products.index', [
            'products' => Product::with(['tiers', 'colours'])->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', ['types' => ProductType::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:120'],
            'quality' => ['required', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $product = Product::create([
            'type' => $data['type'],
            'name' => $data['name'],
            'quality' => $data['quality'],
            'slug' => $this->uniqueSlug($data['name'].' '.$data['quality']),
            'active' => false,
            'sort' => Product::count(),
        ]);

        $product->tiers()->create(['min_qty' => 1, 'unit_price' => $data['price']]);

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product created. It stays hidden until you add colours, sizes and a print area, then switch it on.');
    }

    public function edit(Product $product): View
    {
        $product->load(['tiers', 'colours.mockups', 'variants', 'printAreas']);

        return view('admin.products.edit', [
            'product' => $product,
            'types' => ProductType::cases(),
            'scopes' => TierScope::cases(),
            'sizes' => $product->variants->groupBy('size')->map(fn ($v) => $v->first()->size_surcharge),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:120'],
            'quality' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'included_print_areas' => ['required', 'integer', 'min:0', 'max:5'],
            'tier_scope_override' => ['nullable', Rule::enum(TierScope::class)],
            'active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.min_qty' => ['required', 'integer', 'min:1'],
            'tiers.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $tiers = collect($data['tiers'])->unique('min_qty')->sortBy('min_qty')->values();

        if ($tiers->first()['min_qty'] !== 1) {
            return back()->withInput()->withErrors(['tiers' => 'The first price tier must start at quantity 1.']);
        }

        if ($data['active'] ?? false) {
            $problem = $this->readinessProblem($product);

            if ($problem) {
                return back()->withInput()->withErrors(['active' => $problem]);
            }
        }

        DB::transaction(function () use ($request, $product, $data, $tiers) {
            $attributes = collect($data)->only(['type', 'name', 'quality', 'description', 'included_print_areas', 'tier_scope_override', 'active', 'sort'])->all();
            $attributes['tier_scope_override'] = ($attributes['tier_scope_override'] ?? null) ?: null;
            $attributes['sort'] = $attributes['sort'] ?? $product->sort;
            $attributes['active'] = (bool) ($attributes['active'] ?? false);

            if ($request->hasFile('image')) {
                $old = $product->image;
                $attributes['image'] = $request->file('image')->store('products', 'public');

                if ($old && ! str_starts_with($old, 'media/') && ! str_starts_with($old, 'mockups/')) {
                    Storage::disk('public')->delete($old);
                }
            }

            $product->update($attributes);
            $product->tiers()->delete();
            $product->tiers()->createMany($tiers->map(fn ($t) => ['min_qty' => $t['min_qty'], 'unit_price' => $t['unit_price']])->all());
        });

        return back()->with('status', 'Product saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    /** A product can only be shown in the shop when a customer could actually design and order it. */
    private function readinessProblem(Product $product): ?string
    {
        if (! $product->variants()->where('active', true)->exists()) {
            return 'Add at least one colour and size before switching the product on.';
        }

        if (! $product->printAreas()->where('active', true)->exists()) {
            return 'Add at least one print area before switching the product on.';
        }

        return null;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base);
        $candidate = $slug;
        $i = 2;

        while (Product::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        return $candidate;
    }
}
