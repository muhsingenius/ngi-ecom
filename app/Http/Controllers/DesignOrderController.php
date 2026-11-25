<?php

namespace App\Http\Controllers;

use App\Models\CartLine;
use App\Models\Design;
use App\Models\Product;
use App\Services\CartService;
use App\Support\Money;
use App\Support\PrivateFile;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The "how many, which sizes, which names" step after a design is saved. */
class DesignOrderController extends Controller
{
    public function show(Request $request, Design $design, CartService $cart): View
    {
        abort_unless($design->isOwnedBy($request), 404);

        $design->load(['product.tiers', 'product.colours' => fn ($q) => $q->where('active', true), 'product.variants' => fn ($q) => $q->where('active', true), 'areas.printArea.mockups']);
        $product = $design->product;
        abort_unless($product->active, 404);

        $existing = CartLine::ownedBy($request)->where('design_id', $design->id)->first();
        $sizes = $product->variants->pluck('size')->unique()->values();
        $variants = [];

        foreach ($product->variants as $variant) {
            $variants[$variant->product_colour_id][$variant->size] = ['id' => $variant->id, 'surcharge' => Money::toMinor($variant->size_surcharge)];
        }

        // The preview shows the area that carries names/numbers if there is one.
        $previewArea = $design->areas->first(fn ($a) => $a->personalisation) ?? $design->areas->first();

        $config = [
            'designId' => $design->id,
            'colourId' => $design->product_colour_id,
            'colours' => $product->colours->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => $c->hex])->values(),
            'sizes' => $sizes,
            'variants' => $variants,
            'needsName' => $cart->needs($design, 'name'),
            'needsNumber' => $cart->needs($design, 'number'),
            'existing' => $existing?->items,
            'notes' => $existing?->notes,
            'maxQty' => CartService::MAX_LINE_QTY,
            'preview' => $previewArea ? [
                'image' => $previewArea->preview_path ? PrivateFile::url($previewArea->preview_path, now()->addHour()) : null,
                'mockups' => $previewArea->printArea->mockups->mapWithKeys(fn ($m) => [$m->product_colour_id => $m->url()]),
                'box' => ['x' => (float) $previewArea->printArea->box_x, 'y' => (float) $previewArea->printArea->box_y, 'w' => (float) $previewArea->printArea->box_w, 'h' => (float) $previewArea->printArea->box_h],
                'widthCm' => $previewArea->printArea->width_cm,
                'heightCm' => $previewArea->printArea->height_cm,
                'roles' => $previewArea->personalisation,
            ] : null,
            'symbol' => Money::symbol(),
            'urls' => ['quote' => route('cart.quote'), 'store' => route('cart.store')],
        ];

        return view('designs.order', [
            'design' => $design,
            'product' => $product,
            'config' => $config,
            'fonts' => config('designer.fonts'),
            'others' => Product::active()->where('id', '!=', $product->id)->orderBy('sort')->get(),
        ]);
    }
}
