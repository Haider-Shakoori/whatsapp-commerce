<?php

namespace App\Messaging;

use App\Messaging\Exceptions\DeliveryUncertain;
use App\Models\MessagingChannel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class RelayWaClient
{
    public function session(MessagingChannel $channel): array
    {
        $response = $this->request($channel, 'GET', 'v1/sessions/'.$channel->external_account_id);

        if (! $response->successful()) {
            throw new RuntimeException('RelayWA session lookup failed (HTTP '.$response->status().').');
        }

        $data = $response->json('data', $response->json());
        if (! is_array($data) || ($data['id'] ?? null) !== $channel->external_account_id) {
            throw new RuntimeException('RelayWA returned an unexpected session ID.');
        }

        return $data;
    }

    /**
     * The RelayWA webhook carries the inbound database messageId but not its text.
     * Fetch through the documented session-scoped messages.read endpoint.
     */
    public function inboundMessage(MessagingChannel $channel, string $messageId): array
    {
        if (! preg_match('/^[a-f0-9-]{36}$/iD', $messageId)) {
            throw new RuntimeException('Invalid inbound RelayWA message identifier.');
        }

        $response = $this->request($channel, 'GET',
            'v1/sessions/'.$channel->external_account_id.'/messages/'.$messageId);

        if (! $response->successful()) {
            throw new RuntimeException('RelayWA inbound message lookup failed (HTTP '.$response->status().').');
        }

        $message = $response->json('data', $response->json());
        if (! is_array($message)
            || ($message['id'] ?? null) !== $messageId
            || ($message['session_id'] ?? null) !== $channel->external_account_id) {
            throw new RuntimeException('RelayWA returned an unexpected inbound message.');
        }

        return $message;
    }

    public function sendText(MessagingChannel $channel, string $recipient, string $text, string $idempotencyKey): string
    {
        try {
            $response = $this->request($channel, 'POST', 'v1/sessions/'.$channel->external_account_id.'/messages/text', [
                'to' => $recipient,
                'text' => $text,
                'clientMessageId' => $idempotencyKey,
            ]);
        } catch (ConnectionException $e) {
            // The worker may have already sent the message before the request timed out.
            throw new DeliveryUncertain('RelayWA did not acknowledge the request. Inspect delivery status before retrying.', 0, $e);
        }

        if ($response->serverError()) {
            throw new DeliveryUncertain('RelayWA server did not positively acknowledge delivery.');
        }
        if (! $response->successful()) {
            throw new RuntimeException('RelayWA rejected message request (HTTP '.$response->status().').');
        }

        $data = $response->json('data', $response->json());
        $id = is_array($data) ? ($data['id'] ?? null) : null;
        if (! is_string($id) || $id === '') {
            throw new DeliveryUncertain('RelayWA returned no message ID. Inspect status before retrying.');
        }

        return $id;
    }

    public function request(MessagingChannel $channel, string $method, string $path, array $body = []): Response
    {
        $base = rtrim((string) config('commerce.relaywa_base_url'), '/');
        $parts = parse_url($base);
        if ($base === '' || ! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true)
            || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            throw new RuntimeException('A trusted HTTPS RelayWA base URL must be configured by the platform.');
        }

        $credentials = $channel->credentials ?? [];
        $token = $credentials['api_key'] ?? null;
        if ($channel->provider !== 'relaywa' || ! is_string($token) || $token === '') {
            throw new RuntimeException('RelayWA session API credentials are not configured.');
        }

        $url = $base.'/'.ltrim($path, '/');
        $request = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout((int) config('commerce.relaywa_connect_timeout', 5))
            ->timeout((int) config('commerce.relaywa_request_timeout', 20));

        return $method === 'GET' ? $request->get($url) : $request->post($url, $body);
    }
}
