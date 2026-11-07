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
  @if($canReply && $conversation->status === 'open')
    <form method="post" action="{{ route('merchant.inbox.reply', [$merchant, $conversation]) }}">@csrf
      <div class="field"><label for="reply">Reply to customer</label><textarea id="reply" name="body" rows="3" maxlength="2000" required placeholder="Type a reply">{{ old('body') }}</textarea></div>
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
<p class="muted">Private staff notes are kept on the customer profile. They are never sent to WhatsApp.</p>
</div>
</div>
@endsection
