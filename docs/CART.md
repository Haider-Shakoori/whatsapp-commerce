# Batch 05 — Persistent WhatsApp conversation carts

Customers browse MENU → category → product, reply ADD or ADD 2, select any required variant and modifier group, then use CART to review the basket.
Variants are paginated. Required toppings enforce minimum and maximum choices; optional groups can be skipped with 0.

Commands: CART, ADD [quantity], REMOVE [item], QTY [item] [quantity], CLEAR then CLEAR YES. BACK, NEXT, PREV, STOP, START and MENU remain available.
CHECKOUT, ORDER and PAY explicitly report that checkout is not implemented; no orders or payments are generated.

Cart records are stored by merchant/channel/customer, independent of RelayWA. A line's selection fingerprint normalizes the variant and sorted option IDs, and a line carries a product/name/price snapshot in integer minor units.
Amounts are provisional: checkout must recheck current prices and available inventory atomically. Carts do NOT reserve stock.
Stock is checked for the current cart when adding and updating quantities, including combined quantities of multiple variants.

Webhook signing, encrypted message storage, inbound message deduplication and outbound idempotency continue to apply. Retried envelopes referring to the same RelayWA messageId cannot apply ADD twice.
Default messaging driver remains disabled. Live WhatsApp connections, browser QA, concurrency/load testing and MySQL deployment validation are NOT claimed.

Branch feat/batch-05-conversational-cart builds on PR #4. PRs #1 through #5 must be merged in order.