@extends('layouts.app')
@section('title', 'Message operations')
@section('content')
<div class="ws-page-intro"><div><span class="ws-eyebrow">MESSAGE OPERATIONS</span><h1>Message delivery records</h1>
<p>Recorded outbound states and processing diagnostics. A queued record is not evidence of successful delivery.</p></div>
<a href="{{ route('platform.health.index') }}" class="btn btn-light">System health &rarr;</a></div>
<section class="ws-stat-grid" aria-label="Outbound status counts">
    @foreach(['queued' => 'Queued', 'sending' => 'Sending', 'sent' => 'Reported sent', 'failed' => 'Failed', 'uncertain' => 'Uncertain'] as $state => $title)
        <div class="ws-stat-card"><small>{{ $title }} · {{ $selectedDays }}d</small><strong>{{ number_format($statusCounts[$state] ?? 0) }}</strong></div>
    @endforeach
    <div class="ws-stat-card"><small>Unprocessed inbound (all time)</small><strong>{{ number_format($unprocessedInbound) }}</strong></div>
</section>
<div class="card">
<form method="get" action="{{ route('platform.messages.index') }}" class="ws-toolbar">
<div class="field search"><label for="q">Merchant name or slug</label><input id="q" name="q" type="search" maxlength="100" value="{{ request('q') }}"></div>
<div class="field"><label for="status">Outbound state</label><select id="status" name="status"><option value="">All states</option>
@foreach(['queued','sending','sent','failed','uncertain'] as $state)<option value="{{ $state }}" @selected(request('status') === $state)>{{ ucfirst($state) }}</option>@endforeach</select></div>
<div class="field"><label for="days">Period (UTC)</label><select name="days" id="days">@foreach([7,30,90] as $days)<option value="{{ $days }}" @selected($selectedDays === $days)>Last {{ $days }} days</option>@endforeach</select></div>
<button class="btn" type="submit">Filter</button><a class="btn btn-light" href="{{ route('platform.messages.index') }}">Clear</a>
</form></div>
<div class="card" style="margin-top:18px">
<div class="ws-panel-heading"><div><h2>Outbound record timeline</h2><p>Identifiers, timestamps and attempts only — no recipient phone numbers, chat bodies, credentials or provider IDs.</p></div><span class="pill">{{ number_format($messages->total()) }} matching</span></div>
<div class="table-wrap"><table><thead><tr><th>Record</th><th>Merchant</th><th>Channel</th><th>State</th><th>Attempts</th><th>Created (UTC)</th><th>Last updated (UTC)</th></tr></thead>
<tbody>@forelse($messages as $message)
<tr><td>#{{ $message->id }}</td><td>{{ $message->merchant?->name ?? 'Deleted merchant' }}</td>
<td>{{ $message->channel?->display_name ?? 'Channel #'.$message->messaging_channel_id }}</td>
<td><span @class(['ws-status-chip', 'suspended' => in_array($message->status, ['failed','uncertain'], true)])>{{ ucfirst($message->status) }}</span></td>
<td>{{ $message->attempts }}</td><td>{{ $message->created_at?->format('Y-m-d H:i') ?? '—' }}</td><td>{{ $message->updated_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
@empty<tr><td colspan="7">No matching message records.</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:18px">{{ $messages->links() }}</div>
<p class="muted" style="font-size:12px;margin-top:13px">Uncertain sends are never resent automatically. Investigate provider receipts before initiating any manual recovery.</p>
</div>
@endsection
