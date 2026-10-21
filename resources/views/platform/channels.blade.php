@extends('layouts.app')
@section('title', 'WhatsApp channel monitoring')
@section('content')
<div class="ws-page-intro"><div><span class="ws-eyebrow">CHANNEL OPERATIONS</span><h1>WhatsApp channels</h1>
<p>Review merchant connections and the most recently recorded provider state. These are not live session probes.</p></div>
<a class="btn btn-light" href="{{ route('platform.health.index') }}">System health &rarr;</a></div>
<section class="ws-stat-grid" aria-label="Recorded channel states">
    <div class="ws-stat-card"><small>Total channels</small><strong>{{ number_format($statusCounts->sum()) }}</strong></div>
    <div class="ws-stat-card"><small>Last recorded connected</small><strong>{{ number_format($statusCounts['connected'] ?? 0) }}</strong></div>
    <div class="ws-stat-card"><small>Not checked in 30 minutes</small><strong>{{ number_format($staleChecks) }}</strong></div>
</section>
<div class="card">
<form method="get" action="{{ route('platform.channels.index') }}" class="ws-toolbar">
    <div class="field search"><label for="q">Merchant name or slug</label><input id="q" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Find a merchant"></div>
    <div class="field"><label for="status">Last recorded state</label>
    <select id="status" name="status"><option value="">All states</option>
    @foreach($statusCounts as $status => $total)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }} ({{ $total }})</option>@endforeach
    </select></div>
    <button type="submit" class="btn">Filter</button><a href="{{ route('platform.channels.index') }}" class="btn btn-light">Clear</a>
</form>
</div>
<div class="card" style="margin-top:18px">
<div class="ws-panel-heading"><div><h2>Channel directory</h2><p>Metadata only. Provider account IDs, credentials and customer chats are never exposed here.</p></div><span class="pill">{{ $channels->total() }} matching</span></div>
<div class="table-wrap"><table>
<thead><tr><th>Business</th><th>Channel</th><th>Provider</th><th>Recorded state</th><th>Last checked (UTC)</th><th>Added (UTC)</th></tr></thead>
<tbody>@forelse($channels as $channel)
<tr><td><a class="ws-row-title" href="{{ route('platform.merchants.show', $channel->merchant) }}">{{ $channel->merchant?->name ?? 'Deleted merchant' }}</a></td>
<td>{{ $channel->display_name ?? 'Channel #'.$channel->id }}</td>
<td>{{ ucfirst($channel->provider) }}</td>
<td><span @class(['ws-status-chip', 'suspended' => $channel->status !== 'connected'])>{{ ucfirst($channel->status) }}</span></td>
<td>{{ $channel->status_checked_at?->format('Y-m-d H:i') ?? 'Never checked' }}</td>
<td>{{ $channel->created_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
@empty<tr><td colspan="6">No channels match your filters.</td></tr>@endforelse
</tbody></table></div>
<div style="margin-top:18px">{{ $channels->links() }}</div>
</div>
@endsection
