# Security and Failure-Mode Review — Batch 12

## Trust boundaries

1. **Users vs. merchants:** all merchant actions require membership and manager-only operations use explicit pivot roles. Platform administrators are independent from merchant membership and cannot read customer inboxes merely because they are admins.
2. **RelayWA inbound:** webhook authenticity is HMAC checked over timestamp plus raw payload, with strict timestamp tolerance and session mapping; the actual message is read back using the session-scoped API. Duplicate stored message IDs are idempotent. Unsigned, stale or mismatched webhooks are rejected.
3. **RelayWA outbound:** operator-set HTTPS endpoint, no redirects, UUID-shaped session IDs and optional exact host allowlist. In production the allowlist is required by release preflight. All new outbox entries pass merchant plan, quota and global outbound pause; the queue worker checks conditions again before sending. Delivery timeouts/5xx or process crashes are **uncertain**, not replayed.
4. **Payment:** only Stripe sandbox test keys are accepted; HMAC-signed callbacks, exact metadata/amount/currency matching and idempotency prevent falsely paid orders. Return URL alone never marks payment paid. Manual COD/bank receipt is merchant attestation, not independent funds verification.
5. **AI:** only a staff-initiated draft may send the last bounded customer message to the AI provider when explicitly enabled; no automatic sending, no order or payment actions. External data-processing consent and retention/transfer legal review are deployment gates.
6. **Platform:** administrator provisioning is console-only, independent of the merchant owner role. Sensitive platform changes require password, confirmation and an auditable reason. A global pause cannot grant billing capability.

## Residual risks and honest limitations

- Shared-db tenancy relies on correct scoped queries. Current tests cover relevant cross-tenant operations but do not constitute a full formal security proof; run targeted manual penetration tests before launch.
- **Database queue/worker crash:** once a send is claimed, a crash may leave it in `sending` even if the provider delivered it. The scheduled mark-uncertain command detects these without re-sending. A manual upstream reconciliation process must still be operationally designed.
- **Webhook delivery and eventual consistency:** retries after provider downtime, out-of-order events and key rotation need staging test cases against an authorized upstream service; mocked CI is not enough.
- **MySQL concurrency:** record-level locks serialize idempotency and message quota across workers. Enforce transactional access with a suitable InnoDB engine and repeat load tests on realistic tenant traffic.
- **PII/data retention:** encrypt message bodies and notes, but backups, HTTP access logs, order names, phone numbers and provider-side records still require retention, access and deletion policies.
- **Admin MFA:** not built into this release; use a separate identity layer or implement prior to internet-exposed production.
- **Analytics:** 'accepted' does not mean delivered; subscription entitlements are not verified SaaS revenues or payment receipts.
- **Operator actions:** CLI access must be tightly controlled; audit entries with null actor identify server-console provenance, not the human OS user.
- **Key rotation:** encrypted Eloquent casts require continuity of APP_KEY; rotation and restore must be rehearsed.
- **WhatsApp transport:** service availability and policy changes remain outside local code guarantees.

## Gating verification

Run `php artisan commerce:release:check --production`, the full SQLite + MySQL Actions jobs and staged provider delivery tests. Keep `COMMERCE_MESSAGING_DRIVER=disabled` until deployment review is complete. See `docs/RELEASE_RUNBOOK.md`.
