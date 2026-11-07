# Batch 08 — Customer Management & Team Inbox

## Scope
Every merchant has a customer directory keyed by merchant + phone and a dedicated conversation per WhatsApp session + customer. Messages and private notes are encrypted at rest. Owners/managers can rename customers, assign conversations to merchant members, and open/close threads. Staff may read the shared inbox and respond to unassigned or self-assigned open conversations.

## Capture and delivery
RelayWA signed inbound webhooks (existing Batch 3) are resolved through the session-scoped messages.read API when COMMERCE_MESSAGING_DRIVER=relaywa. Messages are recorded even if the automated catalog menu is turned off. The inbox only accepts inbound direct individual WhatsApp chats, excluding groups, broadcasts, malformed phones and outbound echoes. Identical provider message IDs are stored once per conversation; repeated callbacks do not increase unread count.

Outbound messages, both automation-generated and manual staff replies, are linked to the existing durable outbox. The UI shows queue status and accepted/failed/uncertain state; acceptance is not proof of handset delivery. The existing outbox prevents blind retries after uncertain sends.

## Team permissions
- Any authenticated merchant member can view shared threads and mark messages read.
- Owner and manager can assign to a member of the same merchant and close/reopen threads.
- A staff member may reply only if the thread is unassigned or assigned to that member.
- All customer, message, note, assignment and order queries are explicitly tenant-scoped. Notes are private internal information, never sent through RelayWA.
- Search/filters support status, unread, assigned to me, unassigned, customer name or phone.

## Limitations
- An agent availability calendar, SLA tracking, live typing indicators, attachment downloading, contact import, cross-channel unification and realtime websocket UI are not yet enabled.
- No live WhatsApp account, messages, or customer records were accessed; automated tests mock upstream responses.
- The backend only fetches new messages on the webhook event. Historical chat backfill and read receipts to WhatsApp are not implemented.
- This batch is stacked on PR #7; do not deploy while preceding PRs remain open.
