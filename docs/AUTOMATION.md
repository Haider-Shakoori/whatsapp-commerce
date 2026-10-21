# Batch 09 — Merchant Automations & AI Drafts

## Controls
All merchant automation settings are OFF by default. The global RelayWA driver is separately disabled by default. Only customer-initiated direct messages are eligible for rule replies; no mass or unsolicited sending is included.
STOP opts out, START opts in. Shopping/cart/checkout commands and active shopping sessions do not trigger rule replies. Events are deduplicated, and at most one automated reply per conversation every 30 minutes is allowed.

## Merchant business hours, keywords, and team routing
Weekly hours are entered for the merchant's local timezone. After-hours replies require both hours and a message. Overnight shifts are not yet available.
Up to 30 keyword rules may reply, route to a merchant team member, or both. Priority determines the first matching rule. Rules cannot replace an existing assignment.

## Scheduled follow-ups
Staff must explicitly confirm a customer's consent to a specific one-time follow-up. Scheduling is limited to 1–168 hours. If the customer replies, opts out, closes the conversation, or the channel, merchant setting or business-hours checks fail, the message is skipped.
Run Laravel's minute scheduler and configured queue workers. Outbound idempotency is based on the follow-up record ID. Unknown send outcomes are never blindly retried.

## AI reply drafts
AI is staff-triggered only and requires a merchant opt-in with an external-data disclosure. Administrators separately configure COMMERCE_AI_API_KEY and optionally COMMERCE_AI_MODEL. The most recent inbound customer text and merchant guidance are sent to the OpenAI Responses endpoint with store=false. Phone numbers, customer notes and order/payment data are not intentionally included.
Drafts appear in the staff reply editor and must be checked before manual sending. No AI auto-replies or order/payment actions are implemented.

## Verification and deployment
CI uses mocked RelayWA and AI HTTP clients; no live sessions, customer messages, AI API calls or deployment. Validate MySQL concurrency, timezone edge cases, privacy notices, actual webhook delivery and customer consent before production use.
Stack PR #9 on PR #8 and merge all nine PRs in order.