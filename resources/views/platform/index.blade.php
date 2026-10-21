@extends('layouts.app')
@section('title', 'Platform administration')
@section('content')
<h1>Platform administration</h1>
@include('platform.partials.nav')
<p class="muted">Operator metrics for the commerce platform. Revenue reporting is not yet connected to a verified SaaS billing provider.</p>
@if($outboundPaused || $registrationPaused)
<div class="alert"><strong>Platform controls:</strong>
    {{ $outboundPaused ? 'Outgoing messages paused.' : '' }}
    {{ $registrationPaused ? 'New registrations paused.' : '' }}
    <a href="{{ route('platform.settings.index') }}">Manage controls</a>
</div>
@endif
<div class="grid" style="margin:18px 0">
@foreach([
  ['label' => 'Merchants', 'value' => $merchants],
  ['label' => 'Active merchants', 'value' => $activeMerchants],
  ['label' => 'Current evaluations', 'value' => $trialing],
  ['label' => 'Current paid-tier entitlements', 'value' => $activeSubscriptions],
  ['label' => 'Expiring within 7 days', 'value' => $expiringSoon],
  ['label' => 'WhatsApp channels', 'value' => $channels],
  ['label' => 'Connected channels (last recorded)', 'value' => $connectedChannels],
  ['label' => 'Incoming events · 30d', 'value' => $inbound30d],
  ['label' => 'Outgoing queued · 30d', 'value' => $outbound30d],
  ['label' => 'Failed / uncertain outgoing · 30d', 'value' => $outboundFailed30d],
  ['label' => 'Orders created · 30d', 'value' => $orders30d],
  ['label' => 'Plan requests · 30d', 'value' => $pendingRequests],
] as $stat)
<div class="card"><small class="muted">{{ $stat['label'] }}</small><h2>{{ number_format($stat['value']) }}</h2></div>
@endforeach
</div>
<div class="grid" style="align-items:start">
<div class="card">
<h2>Messaging and queue health</h2>
<p>Messages queued / sending / uncertain: <strong>{{ number_format($unresolvedOutbound) }}</strong></p>
<p>Laravel pending jobs: <strong>{{ number_format($pendingJobs) }}</strong></p>
<p>Laravel failed job records: <strong>{{ number_format($failedJobs) }}</strong></p>
<p>Last webhook ingestion: <strong>{{ $lastInbound ?? 'No events' }}</strong></p>
<p class="muted">Counts indicate workload, not actual delivery or guaranteed service uptime. Connected channel status is last recorded, not a live probe.</p>
<h3>Daily outgoing messages (last 30 days, UTC)</h3>
<div class="table-wrap" style="max-height:320px;overflow:auto"><table>
<thead><tr><th>Date</th><th>Queued records</th></tr></thead><tbody>
@forelse($daily as $day)<tr><td>{{ $day->day }}</td><td>{{ number_format($day->total) }}</td></tr>
@empty<tr><td colspan="2">No outgoing activity</td></tr>@endforelse
</tbody></table></div>
</div>
<div class="card">
<h2>Billing status</h2>
<p>Recorded SaaS invoice entries: <strong>{{ $subscriptionInvoices }}</strong></p>
<p class="muted">Subscription payments, payouts, refunds and recurring billing are not connected. A manual grant is not revenue. Merchant order totals must never be treated as platform revenue.</p>
<a href="{{ route('platform.subscriptions.index') }}">Review subscriptions</a>
<h2 style="margin-top:24px">Recent operator activity</h2>
@forelse($recentActivity as $event)
<p><strong>{{ str_replace('_', ' ', $event->action) }}</strong> · {{ $event->created_at->format('Y-m-d H:i') }} UTC
@if($event->merchant_id) · Merchant #{{ $event->merchant_id }}@endif</p>
@empty<p class="muted">No administrator actions logged.</p>@endforelse
<a href="{{ route('platform.audit.index') }}">View full audit log</a>
</div>
</div>
@endsection
