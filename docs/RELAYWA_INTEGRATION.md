# Batch 03: RelayWA connector

## Verified upstream contract

The implementation was checked against the connected RelayWA repository (Haider-Shakoori/wa), including the NestJS sessions controller, message DTO, webhook worker signing method and example Laravel receiver.

- GET /api/v1/sessions/{sessionId}, with sessions.read permission.
- POST /api/v1/sessions/{sessionId}/messages/text with to, text and clientMessageId, with messages.send permission.
- The RelayWA worker sends signed JSON events with id, type, sessionId, createdAt and data.
- It signs timestamp + a period + the EXACT raw HTTP body using HMAC-SHA256; headers are x-relaywa-timestamp and x-relaywa-signature.
- Supported incoming event name: message.received. Provider session health is also polled periodically.

## Set up (testing only)

1. Configure RELAYWA_API_BASE_URL on the Commerce server to an operator-approved HTTPS RelayWA base URL ending in /api; do not allow merchants to change the URL.
2. Keep COMMERCE_MESSAGING_DRIVER=disabled until test sessions are explicitly enabled. This repo contains NO credentials.
3. In RelayWA, create or locate an authorized WhatsApp session. Generate its session-scoped API key with sessions.read and messages.send scopes.
4. In Commerce, open merchant -> Products -> WhatsApp connections. Enter the session UUID, friendly label and session-scoped API key. Commerce verifies the session through RelayWA.
5. Commerce displays a unique webhook URL for the connected channel. Create a RelayWA webhook endpoint pointing to it, selecting message.received, then copy RelayWA's newly generated webhook secret into the Commerce channel webhook-secret form.
6. If local testing, use HTTPS public tunneling with verified access and do not send customer data to test tunnels.
7. Run the Laravel queue worker and scheduler. Periodic session polling uses the artisan command commerce:relaywa:health.

## Safety boundaries

- API tokens, webhook secrets and inbound payloads are encrypted with Laravel encrypted casts.
- The endpoint rejects missing/bad HMAC, timestamps outside five minutes, wrong session IDs and oversized payloads.
- Events are stored once per merchant channel and provider event ID, then processed through Laravel queues.
- Product orders and chat reply automation are NOT implemented in this batch.
- Only the internal CommerceOutbox queues messages, with one clientMessageId per logical message.
- Unknown delivery after timeouts or server errors is flagged uncertain, never automatically retried.
- A crashed worker can leave sending records; scheduled health checks flag old sends uncertain.
- A disabled transport or disconnected session prevents outbound sends.
- Enabling messaging requires merchant permissions, tested session credentials, recipient consent and a staged rollout.

## Deployment

Do not deploy this branch to production directly. PR #1 and PR #2 remain stacked dependencies. Complete end-to-end QA with an authorized test RelayWA session and a test merchant. This repository is public; never commit secrets.

Security note: rate limits are partitioned by webhook channel and sender IP, so high traffic to one merchant does not exhaust another merchant's webhook allowance. Rotating or detaching a channel's credentials requires updating/disabling its RelayWA endpoint manually.
