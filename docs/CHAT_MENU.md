# Batch 04 — Conversational catalog

WhatsApp-first category/product browsing through RelayWA. Not a native WhatsApp catalog.

## RelayWA verified source contract

RelayWA delivers a signed message.received webhook with data.messageId but not the text.
Commerce fetches GET /api/v1/sessions/{sessionId}/messages/{messageId} using the session API key (messages.read required).
Only incoming direct one-to-one text messages are handled; group chats, broadcasts, outbound and unsupported types are ignored.

## Customer commands

- MENU, HI, HELLO, START: open menu and display categories.
- 1–8: choose category or product on the current page.
- NEXT, PREV: paginate; BACK: previous screen.
- STOP: opt out of automatic menu replies; START: explicitly re-enable.
- Cart, order processing, payment, photos and native catalog actions are not included in this batch.

Menu state is stored per merchant channel and customer. Replies are persisted in the encrypted outbound queue.
Bot responses and menu state changes are in the same transaction as the processed inbound event. Unique idempotency keys prevent duplicate replies.
Only active, in-stock products are shown and other merchants' catalog records cannot be browsed.

## Enablement

New channels default to menu_enabled=false. Merchant owner or manager needs a session key, webhook secret, and valid webhook setup before enabling the menu.
Global COMMERCE_MESSAGING_DRIVER remains disabled by default. When testing, API key needs sessions.read, messages.read and messages.send.
Deploy queue workers and scheduler before using the feature. This branch does not connect a real account or send live messages.

## Limitations

The feature is a text-first conversation, not native WhatsApp catalog or interactive WhatsApp buttons.
Cart and checkout are Batch 5; product image sending is a subsequent enhancement.
Tests mock all RelayWA requests; live delivery and MySQL validation remain outstanding.
Stacking: PR 4 is based on PR 3; merge PRs 1,2,3,4 in that order.