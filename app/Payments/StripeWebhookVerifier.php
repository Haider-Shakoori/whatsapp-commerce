<?php

namespace App\Payments;

final class StripeWebhookVerifier
{
    public function verify(string $payload, string $header, ?string $secret): bool
    {
        if (! is_string($secret) || ! str_starts_with($secret, 'whsec_')) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            if (in_array($key, ['t', 'v1'], true) && $value !== '') {
                $parts[$key][] = $value;
            }
        }
        $stamp = $parts['t'][0] ?? '';
        if (! preg_match('/^\d{10}$/D', $stamp) || abs(time() - (int) $stamp) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $stamp.'.'.$payload, $secret);
        foreach ($parts['v1'] ?? [] as $signature) {
            if (preg_match('/^[a-f0-9]{64}$/iD', $signature) && hash_equals($expected, strtolower($signature))) {
                return true;
            }
        }

        return false;
    }
}
