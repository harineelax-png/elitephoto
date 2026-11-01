<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

final class FreeDeliveryOffer
{
    public const MINIMUM_ORDER_VALUE = 599;

    /**
     * Build the progress and add-on options needed to reach the free-delivery minimum.
     *
     * @param Collection<int, mixed> $cartItems
     * @return array{minimum: int, qualifies: bool, amount_remaining: float, suggestions: Collection<int, array{product: Product, quantity: int, addition: float}>}
     */
    public static function forCart(Collection $cartItems): array
    {
        $subtotal = (float) $cartItems->sum(fn ($item) => $item->price * $item->quantity);
        $amountRemaining = max(0, self::MINIMUM_ORDER_VALUE - $subtotal);

        $suggestions = collect();

        if ($amountRemaining > 0) {
            $cartProductIds = $cartItems->pluck('product_id')->filter()->all();

            $suggestions = Product::query()
                ->where('status', 'active')
                ->where('stock', '>', 0)
                ->when($cartProductIds, fn ($query) => $query->whereNotIn('id', $cartProductIds))
                ->orderBy('price')
                ->get()
                ->filter(fn (Product $product) => (float) $product->price > 0)
                ->map(function (Product $product) use ($amountRemaining) {
                    $quantity = (int) ceil($amountRemaining / (float) $product->price);

                    return [
                        'product' => $product,
                        'quantity' => $quantity,
                        'addition' => (float) $product->price * $quantity,
                    ];
                })
                ->filter(fn (array $suggestion) => $suggestion['quantity'] <= $suggestion['product']->stock)
                ->sortBy('addition')
                ->take(3)
                ->values();
        }

        return [
            'minimum' => self::MINIMUM_ORDER_VALUE,
            'qualifies' => $amountRemaining <= 0,
            'amount_remaining' => $amountRemaining,
            'suggestions' => $suggestions,
        ];
    }
}
