<?php

namespace App\Services;

use App\Models\CartLine;
use App\Models\Design;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The cart is a list of lines (one design on one product, with sizes/colours or a
 * roster). Pricing always goes through PriceCalculator so the cart, the quote shown
 * while ordering and the final order can never disagree.
 */
class CartService
{
    public const MAX_LINE_QTY = 5000;

    public function __construct(private PriceCalculator $calculator) {}

    /** @return Collection<int, CartLine> */
    public function lines(Request $request): Collection
    {
        return CartLine::ownedBy($request)
            ->with(['design.areas.printArea', 'design.colour', 'product.tiers', 'product.variants.colour', 'product.printAreas'])
            ->orderBy('id')
            ->get();
    }

    public function count(Request $request): int
    {
        return (int) $this->lines($request)->sum(fn (CartLine $l) => $l->quantity());
    }

    /**
     * Price a set of cart lines (optionally with an extra, not-yet-saved line).
     *
     * @param  Collection<int, CartLine>  $lines
     * @return array{lines: array<mixed, array<string, mixed>>, subtotal: int, quantity: int}
     */
    public function quote(Collection $lines, ?CartLine $pending = null): array
    {
        $all = $lines->reject(fn (CartLine $l) => $pending && $l->design_id === $pending->design_id)->values();

        if ($pending) {
            $all->push($pending);
        }

        return $this->calculator->quote($all->map(fn (CartLine $line) => $this->input($line))->all());
    }

    /** @return array{id: mixed, product: Product, design_key: string, area_ids: list<int>, items: list<array{variant_id: int, qty: int}>} */
    public function input(CartLine $line): array
    {
        $design = $line->design;

        return [
            'id' => $line->design_id,
            'product' => $line->product,
            'design_key' => (string) $design->group_key,
            'area_ids' => $design->areas->pluck('print_area_id')->all(),
            'items' => collect($line->items)->map(fn ($i) => ['variant_id' => (int) $i['variant_id'], 'qty' => (int) $i['qty']])->all(),
        ];
    }

    /**
     * Validate and normalise submitted items for a design's product.
     *
     * @param  array<int, mixed>  $items
     * @return list<array{variant_id: int, qty: int, name?: string, number?: string}>
     *
     * @throws ValidationException
     */
    public function normaliseItems(Design $design, array $items): array
    {
        $product = $design->product()->with('variants')->first();
        $variants = $product->variants->where('active', true)->keyBy('id');
        $needsName = $this->needs($design, 'name');
        $needsNumber = $this->needs($design, 'number');
        $out = [];

        foreach ($items as $i => $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);

            if (! $variants->has($variantId)) {
                throw ValidationException::withMessages(['items' => 'One of the chosen sizes or colours is no longer available.']);
            }

            if ($qty < 1) {
                continue;
            }

            $row = ['variant_id' => $variantId, 'qty' => $qty];

            $name = trim((string) ($item['name'] ?? ''));
            $number = trim((string) ($item['number'] ?? ''));

            if ($name !== '' || $number !== '' || $needsName || $needsNumber) {
                $row['qty'] = 1; // personalised rows are one item each
                $row += ['name' => $name, 'number' => $number];

                $line = $i + 1;

                if ($needsName && $name === '') {
                    throw ValidationException::withMessages(['items' => "Row {$line} needs a name."]);
                }

                if ($needsNumber && $number === '') {
                    throw ValidationException::withMessages(['items' => "Row {$line} needs a number."]);
                }

                if (mb_strlen($name) > 30) {
                    throw ValidationException::withMessages(['items' => "The name on row {$line} is too long (30 characters at most)."]);
                }

                if (mb_strlen($number) > 4) {
                    throw ValidationException::withMessages(['items' => "The number on row {$line} is too long (4 characters at most)."]);
                }
            }

            $out[] = $row;
        }

        $total = array_sum(array_column($out, 'qty'));

        if ($total < 1) {
            throw ValidationException::withMessages(['items' => 'Choose at least one item.']);
        }

        if ($total > self::MAX_LINE_QTY) {
            throw ValidationException::withMessages(['items' => 'That is more than we can take in one go. Please contact us for very large orders.']);
        }

        return $out;
    }

    /** Whether the design has a name/number placeholder, so every item needs one. */
    public function needs(Design $design, string $role): bool
    {
        $design->loadMissing('areas');

        return $design->areas->contains(fn ($area) => isset(($area->personalisation ?? [])[$role]));
    }
}
