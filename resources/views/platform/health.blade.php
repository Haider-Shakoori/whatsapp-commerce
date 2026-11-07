@extends('layouts.app')
@section('title', 'Platform system health')
@section('content')
<div class="ws-page-intro"><div><span class="ws-eyebrow">RELIABILITY CENTER</span><h1>System health</h1>
<p>Read-only operational signals from Commerce's database. This page cannot establish real-time RelayWA, server or payment-provider uptime.</p></div>
<a class="btn btn-light" href="{{ route('platform.settings.index') }}">Safety controls &rarr;</a></div>
@if($outboundPaused || $registrationPaused)
<div class="alert" role="status"><strong>Safety controls active.</strong>
@if($registrationPaused) Registrations paused. @endif
@if($outboundPaused) Outbound messaging paused. @endif
</div>
@endif
<section class="ws-stat-grid" aria-label="Operational warning indicators">
@foreach([
['label' => 'Pending queue jobs', 'value' => $pendingJobs],
['label' => 'Jobs older than 15m', 'value' => $staleQueuedJobs],
['label' => 'Failed queue jobs', 'value' => $failedJobs],
['label' => 'Unprocessed inbound', 'value' => $unprocessedInbound],
['label' => 'Unprocessed older than 15m', 'value' => $oldInbound],
['label' => 'Failed outbound records', 'value' => $failedOutbound],
['label' => 'Uncertain outbound records', 'value' => $uncertainOutbound],
['label' => 'Sending older than 15m', 'value' => $stuckSending],
['label' => 'Channels not checked in 30m', 'value' => $channelsNotChecked],
['label' => 'Subscriptions expiring in 7d', 'value' => $expiringSoon],
] as $stat)
<div class="ws-stat-card"><small>{{ $stat['label'] }}</small><strong>{{ number_format($stat['value']) }}</strong></div>
@endforeach
</section>
<div class="grid" style="align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,370px),1fr))">
<section class="card">
<h2>Latest recorded activity</h2>
<dl class="ws-summary-list">
<div><dt>Last inbound event (UTC)</dt><dd>{{ $lastInbound ?? 'No events' }}</dd></div>
<div><dt>Last outbound record (UTC)</dt><dd>{{ $lastOutbound ?? 'No events' }}</dd></div>
<div><dt>Last channel status check (UTC)</dt><dd>{{ $lastChannelCheck ?? 'Never checked' }}</dd></div>
</dl>
<p class="muted" style="font-size:12px;margin-top:18px">A missing event or a stale status check is not proof of an outage. The queue may be idle, the merchant may have no channels, or external delivery may be disabled.</p>
</section>
<section class="card"><h2>Investigate operational issues</h2>
<p>Filter channel states and message outcomes before intervening.</p>
<div class="ws-card-actions" style="display:flex;gap:10px;flex-wrap:wrap">
<a href="{{ route('platform.channels.index') }}" class="btn btn-light">Inspect channels</a>
<a href="{{ route('platform.messages.index', ['status' => 'failed']) }}" class="btn btn-light">Failed sends</a>
<a href="{{ route('platform.messages.index', ['status' => 'uncertain']) }}" class="btn btn-light">Uncertain sends</a>
</div>
<h3 class="ws-section-title">Safety policy</h3>
<p class="muted">This is a read-only page. Failed-job payloads, raw provider webhooks, customer numbers and message bodies are intentionally excluded. Replays, retries and provider reconnects require a separate audited operator workflow.</p>
</section>
</div>
@endsection
