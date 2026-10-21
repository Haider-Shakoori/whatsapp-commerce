# Batch 07 — Merchant payments and TEST checkout

This phase provides auditable manual payment methods and Stripe test-mode checkout links.
**No live Stripe key, charge, payout, refund, or marketplace collection is enabled.**

## Payments
- Enable COD (cash on delivery / pickup), bank transfer, or Stripe TEST at merchant settings.
- For manual methods a manager chooses the method and confirms RECEIVED using a receipt/reference after checking actual funds; nothing is auto-credited.
- Test Stripe checkout can only be created using sk_test_ credentials; USD/EUR/GBP are permitted for sandbox testing. Each merchant supplies test credentials separately.
- Link generation uses an order-specific idempotency key and creates one payment-attempt record before contacting Stripe.
- The private merchant dashboard displays the Stripe-hosted test checkout link. Commerce does not automatically send it via WhatsApp.
- A merchant-specific signed webhook verifies the raw body with Stripe-Signature (HMAC-SHA256, 5-minute tolerance).
- Paid status requires a matching test session ID, merchant ID, order ID, checkout attempt, amount, currency, and livemode=false with payment_status=paid.
- Duplicate event IDs are deduplicated. Suspicious or unmatched events remain available for operational review; cancelled orders require manual refund investigation.
- Merchant action and scheduled reconciliation re-fetch status server-side. Customer return pages never mark orders paid.
- Paid orders and unresolved online checkout attempts are not cancellable through Batch 6 status changes.

## Configuration
Use the merchant payment settings screen to enable individual methods. Enter only TEST credentials.
Configure the Stripe test webhook endpoint displayed on that screen. Keep credentials out of this public GitHub repository.
Configure Laravel queue workers and scheduler; every 15 minutes the reconcile command checks unresolved test sessions.

## Limitations
No live payment collection; no refunds; no payout splitting or multi-merchant marketplace fund flows.
Manual settlement records merchant confirmation, not independent bank verification.
Do not use one individual Stripe account for other merchants' collections without appropriate authorized platform arrangements.
Before production: MySQL concurrency tests, live merchant payout/legal review, supported-currency validation, and end-to-end sandbox checkout QA.
This PR is stacked on PR #6; merge #1–#7 in order.
