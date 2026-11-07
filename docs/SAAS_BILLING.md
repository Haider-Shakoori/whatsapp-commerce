# Batch 10 — SaaS subscriptions and usage entitlements

## Default behavior
Every new merchant gets a bounded 14-day Evaluation trial, automatically created at registration. Existing merchants are backfilled to Evaluation during the migration without deleting catalog or conversations. Trial expiry is checked on every metered action, not only by the scheduler. Data remains accessible after expiry, but additional metered resources and outgoing WhatsApp messages are blocked.

No subscription provider is connected, and no subscription charges, invoices, automatic renewals or payouts are created in this batch. A separate Stripe test checkout from Batch 7 is for individual merchant product orders, not SaaS subscriptions.

## Provisional plans
Evaluation, Starter and Growth are seeded with configurable limits on linked messaging channels, catalog products, team seats, monthly outgoing messages, rule automations and AI drafting. Pricing fields are null intentionally; visible pricing is 'To be announced', not $0. Entitlements are attached to merchants, never global across tenants.

## Enforcement
- RelayWA channel linking checks the current plan inside the DB transaction after external session verification; concurrent creation serializes on the subscription record.
- Product creation and CSV imports use DB transactions and current plan product capacity. Reimports update existing SKUs without allocating extra capacity.
- All outgoing messages (bot/staff/follow-up) use the same outbox and an atomic monthly UTC counter. Only a newly persisted idempotency key consumes quota. The quota is not refunded for failed or uncertain downstream delivery; usage measures queued messages, not handset delivery.
- Subscription validity is rechecked before a queued message is actually sent. After expiry, queued messages fail safely and are not blindly replayed.
- Automatic responses and follow-ups recheck feature availability at execution time, including after a downgrade or expired trial. Staff AI drafting and automation enabling check the plan.
- A maximum team seat allowance is shown and implemented in the central entitlement service. There is currently no separate team-invite endpoint; any future invite flow must call assertCapacity('members') in the same transaction before adding a member.

## Customer-facing workspace
Open a merchant's Subscription & billing page to see the current plan, expiry/renewal date, quota use and available plan descriptions. Owners/managers can request a tier change, which records an auditable event but does not activate the new plan or charge a card. Requests are rate-limited to one per day. Staff can view subscription summaries but cannot request changes.
Billing activity displays locally recorded invoices if any; none are generated or marked paid by this batch. No real payments should be inferred from a manual entitlement grant.

## Operator-only lifecycle
Plan upgrades and extensions require server CLI access, exact merchant slug, plan name and --confirm:
php artisan commerce:subscription:grant shop-slug growth --months=2 --confirm
The command updates the entitlement window and writes an operator_grant audit event. Without --confirm it is a dry run; it never collects funds or marks an invoice paid.
php artisan commerce:subscription:expire
This reconciles expired trial/active statuses. It is scheduled daily, but entitlement checks fail immediately at period_end even if the cron job is down. auto_renew is always false.

## Release requirements
CI uses SQLite, fake RelayWA transport and no billing credentials; MySQL concurrent capacity tests, migration on a snapshot of existing data, operational grant authorization and billing/legal approval remain release blockers. Do not enable real checkout, subscription invoices or recurring billing until approved. Follow PR stack #1 through #10 in order.