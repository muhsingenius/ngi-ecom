<?php

namespace App\Http\Controllers;

use App\Models\CartLine;
use App\Models\Design;
use App\Services\CartService;
use App\Support\PrivateFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, CartService $cart): View
    {
        $lines = $cart->lines($request);
        $quote = $lines->isEmpty() ? null : $cart->quote($lines);

        return view('cart.index', [
            'lines' => $lines,
            'quote' => $quote,
            'previews' => $lines->mapWithKeys(fn (CartLine $l) => [$l->id => ($area = $l->design->areas->first(fn ($a) => $a->preview_path)) ? PrivateFile::url($area->preview_path, now()->addHour()) : null]),
        ]);
    }

    /** Add a design to the cart, or update the line that already holds it. */
    public function store(Request $request, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'design_id' => ['required', 'integer'],
            'items' => ['required'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $design = Design::with('product')->findOrFail($data['design_id']);
        abort_unless($design->isOwnedBy($request) && $design->product->active, 404);

        $items = is_string($data['items']) ? json_decode($data['items'], true) : $data['items'];
        $items = $cart->normaliseItems($design, is_array($items) ? array_values($items) : []);

        CartLine::updateOrCreate(
            ['design_id' => $design->id, 'user_id' => $request->user()?->id, 'session_token' => $request->user() ? null : $request->session()->get('designer_token')],
            ['product_id' => $design->product_id, 'items' => $items, 'notes' => $data['notes'] ?? null],
        );

        return redirect()->route('cart.index')->with('status', 'Added to your cart.');
    }

    public function destroy(Request $request, CartLine $line): RedirectResponse
    {
        abort_unless(CartLine::ownedBy($request)->whereKey($line->id)->exists(), 404);

        $line->delete();

        return redirect()->route('cart.index')->with('status', 'Removed from your cart.');
    }

    /**
     * Live price for the options page. Includes everything else in the cart so tiers
     * that span designs or the whole order are right, and is deliberately forgiving:
     * the strict checks happen when the line is saved.
     */
    public function quote(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'design_id' => ['required', 'integer'],
            'items' => ['required', 'array', 'max:5000'],
        ]);

        $design = Design::with(['product.variants', 'areas'])->findOrFail($data['design_id']);
        abort_unless($design->isOwnedBy($request), 404);

        $variants = $design->product->variants->where('active', true)->keyBy('id');
        $items = collect($data['items'])
            ->map(fn ($i) => ['variant_id' => (int) ($i['variant_id'] ?? 0), 'qty' => (int) ($i['qty'] ?? 0)])
            ->filter(fn ($i) => $i['qty'] > 0 && $variants->has($i['variant_id']))
            ->values()->all();

        if (! $items) {
            return response()->json(['quantity' => 0]);
        }

        $pending = new CartLine(['design_id' => $design->id, 'product_id' => $design->product_id, 'items' => $items]);
        $pending->setRelation('design', $design);
        $pending->setRelation('product', $design->product->load(['tiers', 'printAreas']));

        $result = $cart->quote($cart->lines($request), $pending);
        $line = $result['lines'][$design->id];

        return response()->json([
            'quantity' => $line['quantity'],
            'tier_quantity' => $line['tier_quantity'],
            'tier_min_qty' => $line['tier_min_qty'],
            'next_tier' => $line['next_tier'],
            'unit_price' => $line['unit_price'],
            'single_price' => $line['single_price'],
            'extra_area_fee' => $line['extra_area_fee'],
            'saving' => $line['saving'],
            'line_total' => $line['line_total'],
            'cart_subtotal' => $result['subtotal'],
        ]);
    }
}
