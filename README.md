# WhatsApp Commerce

Global multi-tenant, WhatsApp-first commerce SaaS. The commerce database owns merchant catalogs, customer orders and payment state. RelayWA and other channels will be adapters, not the source of truth.

## Current development

- **Batch 01:** Laravel 12 foundation, owner registration, merchant membership and roles, categories, tenant-guarded products, order/messaging schema and CI tests.
- **Batch 02 (branch feat/batch-02-catalog):** Product editing, images, variants, restaurant modifier groups, audited stock adjustments, human-friendly currency-aware prices and CSV import. All business operations remain tenant-scoped.
- **Not yet active:** WhatsApp publishing, inbound order conversations, checkout, real payment processing, subscriptions, customer application.

Batch 02 is stacked on Batch 01 while PR #1 remains unmerged. Merge Batch 01 first, then retarget Batch 02 to main.

## Local development

Requires PHP 8.2+ with SQLite/MySQL support, Composer and the usual Laravel extensions.

\`\`\`bash
git clone https://github.com/Haider-Shakoori/whatsapp-commerce.git
cd whatsapp-commerce
git switch feat/batch-02-catalog
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
- Inbound message deduplication and outbound idempotency schema exist, but no messaging transport is enabled.
- Images are limited to validated JPEG/PNG/WebP 4 MB uploads; never trust original filenames for storage paths.
- No live payments, session credentials, or WhatsApp connections are configured.

This repository was public at the time the foundation began. Consider marking it private before adding proprietary business logic.

## Testing

GitHub Actions performs Composer install, application bootstrap, PHPUnit feature tests and Laravel Pint checks. Run \`php artisan test\` locally and inspect the PR status before merging. Never claim production readiness based on scaffold tests alone.
