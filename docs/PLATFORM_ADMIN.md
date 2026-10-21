# Batch 11 — Platform Owner Administration & Analytics

## Purpose and boundaries

The Platform panel is strictly for platform administrators, not merchant owners/managers. A user can hold a platform role separately from merchant membership: platform permission **does not** confer access to customer conversations, encrypted messages, private customer notes or checkout records through the merchant UI. No payment keys or webhook secrets are ever displayed in the platform dashboards.

### Provision an administrator

From an **authorized server terminal only**, first create a normal user using the app, then run:

```bash
php artisan commerce:platform:admin admin@example.invalid grant
php artisan commerce:platform:admin admin@example.invalid grant --confirm
```

The first command is a dry-run; only `--confirm` writes. Revoke with `revoke --confirm`. The command requires an *existing* exact email address and writes a console-source audit event. Public signup cannot create platform administrators. Guard operating system/SSH access carefully, enforce MFA at the identity layer when available, and consider a second-person review before granting production access.

### Dashboard and merchant management

`/platform` displays aggregate merchant counts, actual active subscription entitlement counts (not verified payments), evaluations, renewals approaching within seven days, channel counts, last-recorded connectivity, inbound events, queued/failed/uncertain outbound records and last-30-day message activity, recorded customer order counts, queue backlog, and failed Laravel job entries. These are **operational counters**, not actual delivery confirmation, realtime probes, profitability or billing revenue.

`/platform/merchants` lists paginated merchant status, subscriptions and resource counts with name/slug/status filtering. The detail screen gives staff/role references, counts, and latest subscription events and admin audits, but intentionally excludes message bodies and merchant credentials. A platform administrator may suspend/reactivate a merchant by entering an audited reason, their own current password and SUSPEND/REACTIVATE. Suspended merchants cannot access the merchant workspace or create/send outgoing messages; underlying data is preserved. Reactivation does not bypass subscription expiry or platform pause.

### Subscription oversight

`/platform/subscriptions` shows merchant subscriptions, period ends and upgrade requests. A platform administrator may explicitly grant a Starter/Growth entitlement (1–24 months) using their current password, reason and GRANT confirmation. The action logs the previous and new plan and **billing_collected=false**, and creates no invoice, Stripe charge, paid status, payout or recurring renewal. This is a manual entitlement grant; it is **not evidence of payment**. Enabling actual SaaS subscription payments and external provider reconciliation is a separate future project.

### Platform safety settings

`/platform/settings` controls two allowlisted booleans:
- `registration_paused`: HTTP 503 for public registration GET/POST (login remains available).
- `outbound_paused`: prevents new outgoing outbox messages and checks again before worker transport sends (cannot recall previously accepted sends).

Settings require current-password verification, typed APPLY, an audit reason and are persisted in `platform_settings`. Disabled/global RelayWA transport is a separate safety switch; these controls cannot enable it. A lost scheduler must not bypass subscription expiry because entitlements are checked on the action path.

### Audit trail

`/platform/audit` is a paginated history with actor user ID, target/merchant, action, time, reason and nonsecret changes. Table rows are append-only within the admin UI. Database operators can still modify the database; treat DB backup, access control and centralized audit log export as production controls.

## Test coverage and deployment limitations

Regression suite covers guest/regular merchant denial, console dry-run/grant/revoke, dashboard tenant data boundaries, suspension/rehabilitation, registration/outbound pauses, high-risk confirmation/password challenges, cross-role access and unbilled plan grants. Checks run with SQLite and mocked providers. No live customer messages, bill payments, Stripe charges, production administration or deployments have been performed.

Before deployment: review MySQL concurrency and large-query EXPLAIN plans, queue health alerts and retention, restore tests, admin MFA and access approvals, actual channel health vs. last recorded status, and signed provider webhooks. PR #11 stacks on PR #10; merge PRs #1–#11 in order and re-run CI against the final integration branch.
