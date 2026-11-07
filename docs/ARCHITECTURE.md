# Architecture Decision Record — Batch 01

## Product principle

Products, money, orders, customer identity, inventory, and merchant data belong to the commerce platform, not to a WhatsApp connection or a specific messaging engine.

## Boundaries

- **Merchant** is the tenant root. Memberships are stored in merchant_user with owner/manager/staff roles.
- **Catalog** has merchant-bound categories, products and variants. All URLs include the merchant slug and are protected by membership middleware.
- **Commerce** has order and order-item tables with immutable checkout price/name snapshots; operational checkout endpoints come in a later batch.
- **Messaging** has merchant-bound channels, durable event and outbox tables, and a MessagingTransport contract.
- **Providers**: Baileys/RelayWA, Whatsmeow, Chromium-backed engines or others are future interchangeable adapters; no live account coupling exists today.
- **Payments**: No payment gateway credentials, payment callbacks or claims of successful payment in Batch 01.

## Security invariants

1. Browser catalog operations must scope every read/write to the authenticated merchant.
2. Never trust a category, product or variant ID without verifying tenant ownership.
3. Keep provider credentials encrypted at rest and omitted from serialized models.
4. Treat inbound webhooks as untrusted: validate signatures or provider authentication, deduplicate IDs, and persist before processing.
5. Reconcile duplicate/unknown payment outcomes using provider-generated idempotency references.
6. Do not treat a queued notification as delivered.
7. Avoid fan-out on disconnect; unsafe bulk retries can duplicate messages.
8. Enforce roles on merchant writes (owner/manager only), with staff as read-only until more granular policy controls exist.
9. Do not store real credentials, live session data or test customer messages in Git.
10. Merchant account suspension must not delete previously placed orders.

## Known scope limits

- This batch intentionally has no WhatsApp login, webhook route, live sending, payment integration, AI assistant, public menu, checkout or product-image upload.
- Catalog price input uses integer minor units until locale-aware currency exponent formatting is introduced.
- No tenant-aware write services exist for order and messaging tables yet: future writes must enforce owner/tenant integrity.
- No subscription/paywall, email verification, password reset, staff invitations or two-factor authentication yet.
- For production: add email verification, rate-limiting policy, security review, object storage and backup/restore verification.

## Next recommended batches

02 Product CRUD, multi-image upload, variants/modifier groups, imports, merchant settings.
03 Channel adapters and test-only RelayWA connectivity; secure webhook ingestion.
04 Conversation engine and catalog/menu browser.
05 Checkout, stock reservation, idempotent orders and merchant notifications.
06 Payment links and gateway-webhook reconciliation.
