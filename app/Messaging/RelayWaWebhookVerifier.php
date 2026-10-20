<?php

namespace App\Messaging;

use Illuminate\Http\Request;

final class RelayWaWebhookVerifier
{
    public function valid(Request $request, string $secret): bool
    {
        $timestamp = $request->header('x-relaywa-timestamp', '');
        $signature = $request->header('x-relaywa-signature', '');

        if ($secret === '' || ! is_string($timestamp) || ! preg_match('/^\d{10}$/D', $timestamp)
            || abs(time() - (int) $timestamp) > (int) config('commerce.webhook_timestamp_tolerance', 300)
            || ! is_string($signature) || ! preg_match('/^sha256=[a-f0-9]{64}$/iD', $signature)) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        return hash_equals($expected, strtolower($signature));
    }
}
