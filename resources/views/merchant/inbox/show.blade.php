@extends('layouts.app')
@section('title', 'Conversation · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.inbox.index', $merchant) }}">&larr; Team inbox</a>
<div class="actions" style="margin:16px 0"><div><h1>{{ $conversation->customer->name ?: $conversation->customer->phone }}</h1>
<p class="muted">{{ $conversation->customer->phone }} · {{ $conversation->channel?->display_name }} · {{ ucfirst($conversation->status) }}
· Assigned: {{ $conversation->assignee?->name ?? 'Nobody' }}</p></div>
<a class="btn btn-light" href="{{ route('merchant.customers.show', [$merchant, $conversation->customer]) }}">Customer profile</a></div>
<div class="grid" style="align-items:start">
<div class="card">
  <div class="actions"><h2>Conversation history</h2>
    <form method="post" action="{{ route('merchant.inbox.read', [$merchant, $conversation]) }}">@csrf<button type="submit" class="btn btn-light">Mark read ({{ $conversation->unread_count }})</button></form>
  </div>
  <div style="display:grid;gap:12px;margin:18px 0;max-height:540px;overflow-y:auto" aria-label="Messages">
  @forelse($messages->getCollection()->reverse() as $message)
    <div style="padding:12px;border-radius:10px;background:{{ $message->direction === 'outbound' ? '#e7f7ef' : '#eef2f5' }};max-width:88%;justify-self:{{ $message->direction === 'outbound' ? 'end' : 'start' }}">
      <small><strong>{{ ucfirst($message->direction) }}</strong> · {{ $message->message_at?->format('M d, H:i') }}
      @if($message->outbound)· {{ $message->outbound->status }}@endif</small>
      <p style="white-space:pre-wrap;overflow-wrap:anywhere;margin:8px 0 0">{{ $message->body ?? '['.$message->message_type.' message]' }}</p>
    </div>
  @empty <p class="muted">No messages yet.</p> @endforelse
  </div>
  {{ $messages->links() }}
  @if($canReply && $conversation->status === 'open' && $automationSettings?->ai_drafts_enabled)
    <div class="card" style="margin:12px 0;background:#f7f9fd">
      <h3>AI reply assistant</h3>
      <p class="muted">AI reads only the latest incoming text and business guidance when you request a draft. The draft is never sent automatically and must be reviewed.</p>
      <form method="post" action="{{ route('merchant.inbox.ai-draft', [$merchant, $conversation]) }}">@csrf
        <button class="btn btn-light" type="submit">Generate draft for review</button>
      </form>
      @if(session('ai_draft'))<p class="muted">Draft placed in the reply box below. Please edit and verify details before sending.</p>@endif
    </div>
  @endif
  @if($canReply && $conversation->status === 'open')
    <form method="post" action="{{ route('merchant.inbox.reply', [$merchant, $conversation]) }}">@csrf
      <div class="field"><label for="reply">Reply to customer</label><textarea id="reply" name="body" rows="3" maxlength="2000" required placeholder="Type a reply">{{ old('body', session('ai_draft')) }}</textarea></div>
      <button class="btn" type="submit">Queue reply</button>
      <small class="muted">Requires a connected RelayWA channel. Delivery is asynchronous; queued does not mean delivered.</small>
    </form>
  @elseif(!$canReply)
    <p class="muted">This conversation is assigned to another team member. Ask a manager to reassign it.</p>
  @endif
</div>
<div class="card">
<h2>Team ownership</h2>
@if($isManager)
  <form method="post" action="{{ route('merchant.inbox.assign', [$merchant, $conversation]) }}">@csrf
    <div class="field"><label for="assigned_to">Assign to</label><select id="assigned_to" name="assigned_to">
      <option value="">Unassigned</option>@foreach($members as $member)
      <option value="{{ $member->id }}" @selected($conversation->assigned_to === $member->id)>{{ $member->name }}</option>
      @endforeach
    </select></div><button class="btn btn-light" type="submit">Update assignment</button>
  </form>
  <form method="post" action="{{ route('merchant.inbox.status', [$merchant, $conversation]) }}" style="margin-top:14px">@csrf
    <input type="hidden" name="status" value="{{ $conversation->status === 'open' ? 'closed' : 'open' }}">
    <button class="btn btn-light" type="submit">{{ $conversation->status === 'open' ? 'Close conversation' : 'Reopen conversation' }}</button>
  </form>
@else <p class="muted">Only owners and managers may change assignments or close conversations.</p>@endif
@if($canReply && $automationSettings?->enabled && $automationSettings->followups_enabled)
<h3 style="margin-top:20px">One-time follow-up</h3>
<p class="muted">Only schedule after confirming customer consent. If the customer replies, opts out or closes this thread, the follow-up is skipped. Delivery outside business hours is skipped.</p>
<form method="post" action="{{ route('merchant.inbox.followups.store', [$merchant, $conversation]) }}">@csrf
  <div class="field"><label for="followup_body">Message</label><textarea id="followup_body" name="body" maxlength="1000" rows="3" required placeholder="Following up on your question..."></textarea></div>
  <div class="field"><label for="delay_hours">Hours from now</label><input type="number" id="delay_hours" name="delay_hours" min="1" max="168" value="24" required></div>
  <div class="field"><label><input type="checkbox" name="consent_confirmed" value="1" required> I confirm this customer has consented to this specific follow-up.</label></div>
  <button class="btn btn-light" type="submit">Schedule follow-up</button>
</form>
@endif
@if($followups->isNotEmpty())
<h3 style="margin-top:20px">Scheduled history</h3>
@foreach($followups as $followup)
<div style="border-top:1px solid #e5e7eb;padding:9px 0">
  <p><strong>{{ ucfirst($followup->status) }}</strong> · {{ $followup->due_at->format('Y-m-d H:i') }} UTC</p>
  @if($canReply && $followup->status === 'scheduled')
  <form method="post" action="{{ route('merchant.inbox.followups.cancel', [$merchant, $conversation, $followup]) }}">@csrf
    <button class="btn btn-light" type="submit">Cancel follow-up</button>
  </form>
  @endif
</div>
@endforeach
@endif
<p class="muted">Private staff notes are kept on the customer profile. They are never sent to WhatsApp.</p>
</div>
</div>
@endsection
