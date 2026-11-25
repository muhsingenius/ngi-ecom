<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Paystack client on Laravel's HTTP client (deliberately no SDK).
 * Amounts are in pesewas, the currency is GHS, and card plus Mobile Money are
 * both available to the customer on Paystack's hosted page.
 */
class Paystack
{
    private const BASE = 'https://api.paystack.co';

    public function configured(): bool
    {
        return filled(config('shop.paystack.secret_key'));
    }

    /**
     * Start a transaction and return Paystack's hosted payment URL.
     *
     * @return array{authorization_url: string, reference: string}
     */
    public function initialize(Order $order, string $reference, string $callbackUrl): array
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => $order->email,
            'amount' => Money::toMinor($order->total),
            'currency' => $order->currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => ['order_number' => $order->number, 'customer' => $order->customer_name, 'phone' => $order->phone],
        ]);

        if (! $response->ok() || ! $response->json('status')) {
            throw new RuntimeException('Paystack could not start the payment: '.($response->json('message') ?? $response->status()));
        }

        return ['authorization_url' => $response->json('data.authorization_url'), 'reference' => $response->json('data.reference')];
    }

    /** Server-to-server check of a transaction (never trust a browser redirect). */
    public function verify(string $reference): array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference));

        if (! $response->ok()) {
            throw new RuntimeException('Paystack could not verify the payment: '.($response->json('message') ?? $response->status()));
        }

        return (array) $response->json('data');
    }

    /** @return array<string, mixed> */
    public function refund(string $reference, int $amountMinor): array
    {
        $response = $this->client()->post('/refund', ['transaction' => $reference, 'amount' => $amountMinor]);

        if (! $response->ok() || ! $response->json('status')) {
            throw new RuntimeException('Paystack could not refund the payment: '.($response->json('message') ?? $response->status()));
        }

        return (array) $response->json('data');
    }

    /** Webhook authenticity: HMAC-SHA512 of the raw body with the secret key. */
    public function validSignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('shop.paystack.secret_key');

        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    private function client(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('Online payment is not set up yet.');
        }

        return Http::baseUrl(self::BASE)->withToken((string) config('shop.paystack.secret_key'))->acceptJson()->asJson()->timeout(20);
    }
}
