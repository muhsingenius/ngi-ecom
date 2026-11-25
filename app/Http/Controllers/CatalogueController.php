<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\Product;
use App\Services\PriceCalculator;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogueController extends Controller
{
    public function index(Request $request): View
    {
        $type = ProductType::tryFrom((string) $request->query('type'));

        $products = Product::query()
            ->active()
            ->with(['tiers', 'colours' => fn ($q) => $q->where('active', true)])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('sort')->orderBy('id')
            ->get();

        return view('catalogue.index', ['products' => $products, 'type' => $type, 'types' => ProductType::cases()]);
    }

    public function show(Product $product, PriceCalculator $prices): View
    {
        abort_unless($product->active, 404);

        $product->load(['tiers', 'colours' => fn ($q) => $q->where('active', true), 'colours.mockups', 'variants', 'printAreas' => fn ($q) => $q->where('active', true)]);

        $surcharges = $product->variants->where('active', true)->where('size_surcharge', '>', 0)
            ->groupBy('size')->map(fn ($v) => Money::toMinor($v->first()->size_surcharge));

        return view('catalogue.show', [
            'product' => $product,
            'scope' => $product->tier_scope_override ?? $prices->defaultScope(),
            'surcharges' => $surcharges,
            'tiersJson' => $product->tiers->map(fn ($t) => ['min' => $t->min_qty, 'price' => Money::toMinor($t->unit_price)])->values(),
        ]);
    }
}
