# Batch 06 — WhatsApp checkout and merchant order management

Customer text workflow: CART -> CHECKOUT -> name -> PICKUP or DELIVERY -> delivery address (if selected) -> review product subtotal -> CONFIRM. CANCEL aborts the draft while preserving the cart. MENU and STOP cancel any open checkout draft. Duplicate confirmations cannot produce duplicate orders.

The shipping/delivery charge is **not configured** and is stored as zero with an explicit `delivery_fee_status=not_configured`. If delivery applies, the merchant must agree on any fee outside the system before fulfilling the order. These orders are created as **unpaid**, and no gateway or COD collection confirmation is performed here.

Order placement uses one database transaction, locks the cart and products/variants, verifies current merchant product prices and selected options, checks aggregate stock across all cart lines, creates immutable snapshots, then decrements tracked stock and empties the cart. Failed confirmation keeps the cart intact. Checkout tokens have a unique order constraint and inbox events are deduplicated by provider message ID.

The merchant dashboard provides tenant-scoped order lists and details. Owner/manager roles may transition pending -> accepted -> preparing -> ready -> completed, or cancel a non-completed order. Cancellation restores tracked inventory exactly once (product-level audit entries are created; a dedicated variant audit ledger is not yet implemented).

Known limits: No live payment collection, no configurable delivery rates, no kitchen tickets, refunds or notifications. Current development CI uses in-memory SQLite and mocked RelayWA; MySQL concurrency tests, real session tests and production browser QA are release gates. PR #6 depends on PRs #1–#5.
