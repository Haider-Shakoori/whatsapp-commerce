# WhatsApp Commerce

Multi-tenant, WhatsApp-first commerce SaaS foundation. Merchants manage catalogs and orders in **our own database**; messaging providers such as RelayWA are integration channels, not the system of record.

## Batch 01 status

Implemented:
- Laravel 12 application bootstrap and server-rendered responsive merchant pages.
- Owner registration, login/logout, session security and business onboarding.
- Shared-database tenant model and merchant membership guard.
- Categories and product creation/listing with tenant-scoped validation.
- Database foundations for variants, orders, immutable order-item snapshots, channels, inbound event deduplication and outbound message idempotency.
- MessagingTransport interface bound to a deliberately disabled driver; **no WhatsApp connection is made**.
- Automated feature tests and GitHub Actions CI.

Not yet implemented: native WhatsApp menus, provider connectivity, customer chatbot, live ordering, payment processing, product images, full editing/imports, staff invitations and subscriptions.

## Requirements

- PHP >= 8.2 with PDO SQLite (for local quick start) or PDO MySQL.
- Composer.
- MySQL 8+ recommended for production, SQLite for tests/development.
- Do not run production traffic before security, gateway and order-flow reviews.

## Local quick start

1. Clone repository and run `composer install`.
2. Copy `.env.example` to `.env`, then `php artisan key:generate`.
3. For SQLite, run `touch database/database.sqlite` (on Windows create an empty file manually) and set `DB_CONNECTION=sqlite`.
4. Run `php artisan migrate`.
5. Run `php artisan serve`; register a merchant at `/register`.
6. Run `php artisan test`.

For MySQL, set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in the uncommitted `.env`.

## Security and reliability notes

A merchant can only access another merchant's records if they belong to that business. Each catalog query/write must remain scoped to its merchant. Authentication and catalog creation are intended as the foundation; production onboarding still needs email verification and further abuse prevention.

Provider connection credentials must never be committed to Git. The schema supports encrypted channel credentials but has no public configuration endpoints yet.

The checkout/order tables are preparatory structures; **no ordering or payment is active**.

Read [architecture decisions](docs/ARCHITECTURE.md) before extending the platform.

## Branch policy

All Batch 01 changes are proposed via `feat/batch-01-foundation`. Review automated tests and merge the pull request after validation.
