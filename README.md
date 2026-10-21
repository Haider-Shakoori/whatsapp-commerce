# WhatsApp Commerce

Global multi-tenant, WhatsApp-first commerce SaaS. The commerce database owns merchant catalogs, customer orders and payment state. RelayWA and other channels will be adapters, not the source of truth.

## Current development

- **Batch 01:** Laravel 12 foundation, owner registration, merchant membership and roles, categories, tenant-guarded products, order/messaging schema and CI tests.
- **Batch 02 (branch feat/batch-02-catalog):** Product editing, images, variants, restaurant modifier groups, audited stock adjustments, human-friendly currency-aware prices and CSV import. All business operations remain tenant-scoped.
- **Batch 03:** RelayWA verified session mapping, secure signed inbound webhooks, encrypted credentials, message outbox and health monitoring.
- **Batch 04 (branch feat/batch-04-chat-menu):** Merchant-controlled text-based WhatsApp menu, category and product browsing, variants/extras, pagination, opt-out and event deduplication.
- **Batch 05 (branch feat/batch-05-conversational-cart):** Persistent chat shopping carts, variant and modifier selections, quantity changes, stock checks, provisional totals, tenant isolation and replay-safe ADD commands.
- **Not yet active:** Checkout/orders, real payment processing, photo messages, native WhatsApp catalog, subscription billing and customer checkout.

Development PRs are currently stacked: PR #1 → #2 → #3 → #4 → #5. Merge in this order and rerun CI before deployment.

## Local development

Requires PHP 8.2+ with SQLite/MySQL support, Composer and the usual Laravel extensions.

\`\`\`bash
git clone https://github.com/Haider-Shakoori/whatsapp-commerce.git
cd whatsapp-commerce
git switch feat/batch-05-conversational-cart
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan storage:link
php artisan serve
php artisan test
\`\`\`

Create the SQLite file manually on Windows or configure MySQL using the uncommitted \`.env\`. Do not commit API credentials or merchant data.

### Merchant workflows

1. Register a business and create categories.
2. Add products using normal decimal prices and optionally initial stock.
3. Open **Edit / options** to upload images, create size/color variants, and add optional/required modifiers such as restaurant toppings.
4. Adjust tracked stock using a reasoned audit entry; updating product details cannot silently change stock.
5. Upload a UTF-8 CSV (up to 500 records, 2 MB max) with required \`sku,name,price\` and optional \`category,description,stock,active\`. Sample: \`public/samples/products.csv\`. Reimport by SKU updates product details but preserves existing stock.

Product images are kept under \`storage/app/public/merchants/{merchant_id}/products/{product_id}\`, with metadata in the database. The public disk must be backed by appropriate private origins or access controls if merchant products are not intended to be public.

## Currency

Prices are stored as integer minor units. The dashboard accepts normal decimal prices; fraction precision follows common currency digits (e.g., AFN/USD: 2, JPY: 0, KWD: 3). Currency rules should be reviewed before opening unrestricted country onboarding.

## Architecture and security

- Shared MySQL tenancy using merchant-scoped reads/writes; merchant members see only their workspace and only owner/manager roles can modify catalogs.
- Catalog IDs are checked against the active merchant before modifying product images, variants, modifiers, stock or import rows.
- Signed RelayWA webhook, deduplicated inbound messages and an idempotent outbound queue are implemented; the global outbound transport is disabled by default.
- Images are limited to validated JPEG/PNG/WebP 4 MB uploads; never trust original filenames for storage paths.
- No live payments or WhatsApp sessions were connected; secure merchant credential configuration exists for future test integration.

This repository was public at the time the foundation began. Consider marking it private before adding proprietary business logic.

## Testing

GitHub Actions performs Composer install, application bootstrap, PHPUnit feature tests and Laravel Pint checks. Run \`php artisan test\` locally and inspect the PR status before merging. Never claim production readiness based on scaffold tests alone.


## RelayWA integration (Batch 03)

Batch 03 adds a two-step existing-session onboarding flow, per-merchant encrypted credentials, signed webhook ingestion with replay protection, outbox idempotency and safe delivery states. The adapter is DISABLED by default. See docs/RELAYWA_INTEGRATION.md for details. PR #3 is stacked on PR #2 and is not a production deployment.


## Chat catalog (Batch 04)

The merchant can enable or disable an automated text menu in WhatsApp connections after configuring a signed RelayWA webhook. Customers can send MENU/HI/START, select a category or product using a number, paginate with NEXT/PREV and navigate using BACK. STOP pauses automated replies until START is sent again. The global messaging driver remains disabled by default, and this branch has not been tested against a live WhatsApp number. For setup and limitations see [Chat Menu](docs/CHAT_MENU.md).


## Customer cart (Batch 05)

Customers open the chat MENU, view a product and reply ADD or ADD 2, then select sizes/colors and any required/optional toppings. CART displays subtotal; QTY, REMOVE, CLEAR and CLEAR YES maintain the basket. CHECKOUT is intentionally disabled. Cart prices are provisional, stock is not reserved, and price and stock must be reverified at actual checkout. See [Batch 05 cart documentation](docs/CART.md).


## Checkout & Order Management (Batch 06)

Customers use CHECKOUT from a saved cart, provide name and pickup/delivery details, review an indicative subtotal, then CONFIRM. A confirmed order is placed as unpaid and its items are deducted from tracked inventory in one transaction; failures preserve the cart. Merchants review orders from the dashboard and progress statuses or cancel with stock restoration. No payment integration or delivery-rate calculation is activated. See [Checkout implementation and limitations](docs/CHECKOUT.md).


## Batch 07 — Payments

Merchant COD/manual bank settlement and Stripe TEST-only Checkout links, verified signed webhooks, idempotency and reconciliation. No live charge or payout. See [payment documentation](docs/PAYMENTS.md).


## Batch 08 — Customer Directory & Team Inbox

Shared per-merchant WhatsApp inbox, encrypted message history, customer profiles with internal notes, unread counters, staff replies and managerial assignments. Automated menu replies and manual messages are recorded in one conversation history. More at [inbox implementation](docs/INBOX.md).


## Batch 09 — Automations & AI Drafting

Merchant-controlled keyword/after-hours replies, business hours, routing, one-time consent-based follow-ups, and optional staff-reviewed AI drafts. No AI auto-sending. See docs/AUTOMATION.md.


## Batch 10 — SaaS Subscriptions & Billing Foundation

Merchant-level Evaluation, Starter and Growth plans, a bounded 14-day evaluation, entitlement-checked catalog/channel/outbound workflows, message metering, plan-change requests, billing/event history, operator-only manual grants and daily expiry reconciliation. No live SaaS charges, automatically paid invoices or auto-renewals. [Setup and limitations](docs/SAAS_BILLING.md).
