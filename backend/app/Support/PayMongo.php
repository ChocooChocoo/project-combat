<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The PayMongo Checkout Sessions API (R-35): GCash, Maya and card.
 * A payment counts only when PayMongo itself says so, either through a signed
 * webhook or when the backend asks PayMongo directly. Screens are never trusted.
 */
class PayMongo
{
    private const BASE = 'https://api.paymongo.com/v1';

    /** Signed webhooks older than this are refused, so an old message cannot be replayed. */
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    /** @return array{id: string, checkout_url: string} */
    public function createCheckout(float $amount, string $name, string $description, string $successUrl, string $cancelUrl, array $metadata): array
    {
        $response = $this->client()->post('/checkout_sessions', [
            'data' => ['attributes' => [
                'line_items' => [[
                    'name' => $name,
                    'amount' => (int) round($amount * 100), // centavos
                    'currency' => 'PHP',
                    'quantity' => 1,
                ]],
                'payment_method_types' => ['gcash', 'paymaya', 'card'],
                'description' => $description,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'show_line_items' => true,
                'metadata' => array_map('strval', $metadata),
            ]],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('PayMongo did not create the checkout.');
        }

        return [
            'id' => $response->json('data.id'),
            'checkout_url' => $response->json('data.attributes.checkout_url'),
        ];
    }

    /**
     * Asks PayMongo about one checkout session.
     *
     * @return array{status: string, paid: bool, amount: ?float, payment_id: ?string, checkout_url: ?string}
     */
    public function checkout(string $id): array
    {
        $response = $this->client()->get('/checkout_sessions/'.rawurlencode($id));

        if (! $response->successful()) {
            throw new RuntimeException('PayMongo did not return the checkout.');
        }

        $paid = collect($response->json('data.attributes.payments') ?? [])
            ->first(fn ($payment) => data_get($payment, 'attributes.status') === 'paid');

        return [
            'status' => (string) $response->json('data.attributes.status'),
            'paid' => $paid !== null,
            'amount' => $paid ? data_get($paid, 'attributes.amount') / 100 : null,
            'payment_id' => $paid ? data_get($paid, 'id') : null,
            'checkout_url' => $response->json('data.attributes.checkout_url'),
        ];
    }

    /**
     * Checks a "Paymongo-Signature" header: "t=<time>,te=<test signature>,li=<live signature>".
     * The signature is HMAC-SHA256 of "<time>.<raw body>" with the webhook secret.
     */
    public function validSignature(?string $header, string $body): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');

        if ($secret === '' || ! $header) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key] = $value;
        }

        $time = $parts['t'] ?? '';

        if (! ctype_digit($time) || abs(time() - (int) $time) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$time}.{$body}", $secret);

        foreach (['te', 'li'] as $key) {
            if (! empty($parts[$key]) && hash_equals($expected, $parts[$key])) {
                return true;
            }
        }

        return false;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE)
            ->withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
