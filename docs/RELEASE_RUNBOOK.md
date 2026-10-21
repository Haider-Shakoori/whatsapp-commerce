# WhatsApp Commerce — release and rollback runbook (Batch 12)

**Current release status: NOT DEPLOYED.** This repository contains 12 stacked feature PRs. CI on a feature branch is **not** proof that the PR stack has been merged to the main deployment target or that external messaging/payment integrations work. Deployment and live provider enablement require explicit authorization.

## Release approval gates

- [ ] Merge PRs **#1 → #12, in order**, retargeting the next PR against `main` after each merge. Confirm that no PR has conflicting or unreviewed changes. Run all security and MySQL checks on final `main`, not only a feature branch.
- [ ] Obtain a recent encrypted, tested backup of MySQL, attached customer-upload assets and **the original APP_KEY** (required to decrypt stored merchant message bodies and credentials). Store keys/backup out of Git; test restore to an isolated system.
- [ ] Review production domain/TLS, SMTP, session encryption, trusted proxies, tenant data retention and privacy terms. Do not enable Stripe live charges or set unapproved billing prices; test-only Stripe remains separate from SaaS billing.
- [ ] Provide production MySQL and restricted DB user; verify migration on a **snapshot of real existing data**. Concurrency is covered by DB row locks, but production lock contention, indexes and isolation must be load-tested.
- [ ] Configure queue workers with supervisors and restart behavior. Database queue `retry_after >= 150`; the outbound worker sets `$timeout=90` and `$tries=1`. Application scheduler runs every minute.
- [ ] Require MFA for platform administrators through a configured identity layer or equivalent policy, separate server access from merchant accounts, and audit every admin grant. Current in-app authentication does not implement MFA.
- [ ] Confirm platform and RelayWA operators have authorized the test sessions and user consent. Review external messaging policy risk, signed webhook replay behavior and documented emergency off-switch.
- [ ] Obtain separate approvals for privacy/data transfer before configuring the optional AI draft provider.
- [ ] Verify all jobs, webhook handlers and entitlement checks against an authorized staging RelayWA session. Simulate dropped acknowledgements: messages must go `uncertain`, **never auto-resend**.
- [ ] Sign off on restore, service monitoring, logging retention, runbook ownership and emergency suspension policies.

## Safe staging deployment (not executed by this PR)

Use immutable Git commit SHA from final merged `main`; do not serve the checkout itself as a writable public document root. Use a dedicated non-root system account. Example steps below assume a conventional PHP 8.3+ / Composer / MySQL / Laravel host and must be adapted to the actual hosting environment.

1. Release to an isolated staging URL protected by authentication. Install locked Composer dependencies using `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`. Do not upload local `.env`, `vendor`, private keys or runtime logs to Git.
2. Create `.env` from a secure production secret manager. Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, persistent `APP_KEY`, `DB_CONNECTION=mysql`, restricted DB credentials, `SESSION_ENCRYPT=true`, server-side session storage, `QUEUE_CONNECTION=database`, and `DB_QUEUE_RETRY_AFTER=180`. Initially leave **`COMMERCE_MESSAGING_DRIVER=disabled`**. Never rotate APP_KEY without a deliberate encrypted-data re-encryption plan.
3. After snapshot/backup, run `php artisan migrate --force`, `php artisan optimize:clear`, then `php artisan config:cache`. Never run `migrate:fresh` against existing merchant data.
4. Run **`php artisan commerce:release:check --production`**. It must pass. It validates database schema, environment safety and queue settings; it is not an end-to-end production check. Use `php artisan route:list` and `php artisan migrate:status` for review.
5. Start supervised `php artisan queue:work database --timeout=90 --sleep=3 --max-time=3600` workers and a once-per-minute `php artisan schedule:run` cron job. Restart workers following code/config updates; test crash/restart behavior.
6. Visit only authorized test merchant accounts; check onboarding trial, merchant ownership, catalog creation, order checkout, Stripe **TEST** callbacks, inbox and subscription admin screens. Verify row-level merchant isolation, webhook signature rejection, and paused outbound safety.
7. Configure exact operator-controlled `RELAYWA_API_BASE_URL` plus matching `RELAYWA_ALLOWED_HOST`; load per-merchant session-scoped credentials and webhook secret securely. Enable `COMMERCE_MESSAGING_DRIVER=relaywa` **only after** explicit launch authorization and passing signed delivery/timeout tests.
8. Verify health counters against actual provider logs. A local `accepted` send means upstream acknowledged receipt; it does **not** prove a handset received the message. No official API integration is presumed or activated by this work.
9. Do not connect live Stripe subscriptions, real billing, automatic collection, or external AI until the separate commercial, compliance, data-protection and end-to-end checks are approved.

## Incident response / rollback

- **Immediate outbound stop:** set the Platform admin `outbound_paused` switch, or set `COMMERCE_MESSAGING_DRIVER=disabled` in trusted server config, refresh config cache, restart queue workers. The switch cannot retract already accepted outbound messages.
- **Unknown delivery state:** run `php artisan commerce:outbox:mark-uncertain` to flag stale `sending` rows older than 15 minutes. **Do not automatically replay these sends.** Reconcile message IDs and delivery with RelayWA operational records before choosing any manual action.
- **Queue issue:** suspend queue workers and record backlog, identify affected message IDs; prevent a replay storm by preserving idempotency keys and recording manual interventions.
- **Web/DB rollback:** deploy the last verified immutable app version; do **not** automatically reverse schema migrations if they could drop new business data. Validate schema compatibility first. If data recovery is unavoidable, restore the encrypted snapshot to an isolated database and reconcile updates since backup before any production cutover.
- **Compromised webhook/session secrets:** disable the affected merchant channel, rotate provider credentials and signing secrets, inspect audit/event logs, and verify platform egress whitelist and signed timestamp windows.
- **Platform owner compromise:** revoke via `php artisan commerce:platform:admin <existing-email> revoke --confirm`, reset identity sessions, inspect platform audit and infrastructure logs. Console access itself must be separately protected.
- **No customer data deletion during emergency pause or subscription expiry.** Preserve tenant records for safe recovery.

## What GitHub CI does and does not verify

SQLite tests cover business logic and model regressions; a separate MySQL 8.4 workflow exercises migration/recreation, full feature tests and release preflight under real MySQL. Neither test suite proves multi-process concurrency under production load, connection with the user's live RelayWA VPS, customer consent, rate limits, cloud networking or true payment settlement. Those remain operational acceptance gates.

### Ready-for-launch definition

Not merely '12/12 coded': all PRs merged, checks green on `main`, authorized staging signed-off, production preflight passed, backups/restoration verified, access/MFA approved, provider edge cases tested, and explicit launch approval received.
