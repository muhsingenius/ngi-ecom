<?php

namespace App\Services;

use App\Enums\TierScope;
use App\Models\Product;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Server-side price calculation. The browser shows a live total for convenience,
 * but this is the only place prices are decided (cart, checkout, orders).
 *
 * A "line" is one design on one product, with any mix of sizes and colours in `items`.
 * Sizes and colours always count together towards the quantity tier.
 *
 * Line input:
 *   id          any unique key
 *   product     Product with tiers, variants and printAreas loaded
 *   design_key  identifies the design (the same logo on a polo and a cap shares a key)
 *   area_ids    print areas used
 *   items       list of ['variant_id' => int, 'qty' => int]
 */
class PriceCalculator
{
    public function __construct(private Settings $settings) {}

    public function defaultScope(): TierScope
    {
        return TierScope::tryFrom((string) $this->settings->get('pricing.tier_scope')) ?? TierScope::ProductDesign;
    }

    /**
     * @param  list<array{id: mixed, product: Product, design_key: string, area_ids: list<int>, items: list<array{variant_id: int, qty: int}>}>  $lines
     * @return array{lines: array<mixed, array<string, mixed>>, subtotal: int, quantity: int}
     */
    public function quote(array $lines): array
    {
        $counts = [];
        $prepared = [];

        foreach ($lines as $line) {
            $quantity = $this->quantity($line['items']);
            $scope = $line['product']->tier_scope_override ?? $this->defaultScope();
            $key = $this->tierKey($scope, $line['product'], $line['design_key']);

            $counts[$key] = ($counts[$key] ?? 0) + $quantity;
            $prepared[] = compact('line', 'quantity', 'scope', 'key');
        }

        $result = [];
        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($prepared as ['line' => $line, 'quantity' => $quantity, 'scope' => $scope, 'key' => $key]) {
            $priced = $this->priceLine($line, $quantity, $counts[$key], $scope);
            $result[$line['id']] = $priced;
            $subtotal += $priced['line_total'];
            $totalQuantity += $quantity;
        }

        return ['lines' => $result, 'subtotal' => $subtotal, 'quantity' => $totalQuantity];
    }

    /**
     * Tier price (minor units) a product charges at a given tier quantity.
     */
    public function tierFor(Product $product, int $tierQuantity): array
    {
        $tiers = $product->tiers->sortBy('min_qty')->values();
        $tier = $tiers->last(fn ($t) => $t->min_qty <= max($tierQuantity, 1)) ?? $tiers->first();

        if (! $tier) {
            throw new InvalidArgumentException("Product [{$product->id}] has no price tiers.");
        }

        $next = $tiers->first(fn ($t) => $t->min_qty > $tier->min_qty);

        return [
            'min_qty' => $tier->min_qty,
            'unit_price' => Money::toMinor($tier->unit_price),
            'next' => $next ? ['min_qty' => $next->min_qty, 'unit_price' => Money::toMinor($next->unit_price)] : null,
        ];
    }

    /**
     * Extra cost per item for print areas beyond those included in the base price.
     * The most expensive areas are the ones waived, which favours the customer.
     *
     * @param  list<int>  $areaIds
     */
    public function extraAreaFee(Product $product, array $areaIds): int
    {
        $fees = $product->printAreas
            ->whereIn('id', $areaIds)
            ->where('active', true)
            ->map(fn ($area) => Money::toMinor($area->extra_fee))
            ->sortDesc()
            ->values();

        return (int) $fees->slice(max($product->included_print_areas, 0))->sum();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function priceLine(array $line, int $quantity, int $tierQuantity, TierScope $scope): array
    {
        /** @var Product $product */
        $product = $line['product'];
        $variants = $product->variants->keyBy('id');

        $tier = $this->tierFor($product, $tierQuantity);
        $single = $this->tierFor($product, 1)['unit_price'];
        $extra = $this->extraAreaFee($product, $line['area_ids']);

        $items = [];
        $lineTotal = 0;

        foreach ($line['items'] as $item) {
            $variant = $variants->get($item['variant_id']);

            if (! $variant || ! $variant->active) {
                throw new InvalidArgumentException("Variant [{$item['variant_id']}] is not available for product [{$product->id}].");
            }

            $surcharge = Money::toMinor($variant->size_surcharge);
            $unit = $tier['unit_price'] + $surcharge + $extra;
            $itemTotal = $unit * $item['qty'];

            $items[] = $item + ['unit_price' => $unit, 'size_surcharge' => $surcharge, 'total' => $itemTotal];
            $lineTotal += $itemTotal;
        }

        return [
            'quantity' => $quantity,
            'scope' => $scope,
            'tier_quantity' => $tierQuantity,
            'tier_min_qty' => $tier['min_qty'],
            'next_tier' => $tier['next'],
            'unit_price' => $tier['unit_price'],
            'single_price' => $single,
            'extra_area_fee' => $extra,
            'saving' => max(0, ($single - $tier['unit_price']) * $quantity),
            'items' => $items,
            'line_total' => $lineTotal,
        ];
    }

    /** @param list<array{variant_id: int, qty: int}> $items */
    private function quantity(array $items): int
    {
        return (int) array_sum(array_map(fn ($item) => max(0, (int) $item['qty']), $items));
    }

    private function tierKey(TierScope $scope, Product $product, string $designKey): string
    {
        return match ($scope) {
            TierScope::ProductDesign => "p{$product->id}|d{$designKey}",
            TierScope::Design => "d{$designKey}",
            TierScope::Order => 'order',
        };
    }
}
