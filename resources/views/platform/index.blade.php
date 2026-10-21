@extends('layouts.app')
@section('title', 'Platform administration')
@section('content')
<div class="ws-page-intro">
    <div><span class="ws-eyebrow">PLATFORM INTELLIGENCE</span><h1>Platform administration</h1>
        <p>Merchant activity, subscriptions, messaging and queue health. Figures are recorded operational events, not verified SaaS revenue.</p></div>
    <form method="get" action="{{ route('platform.index') }}" class="ws-date-range">
        <label for="days">Reporting period</label><select name="days" id="days" onchange="this.form.submit()">
            @foreach([7, 30, 90] as $period)<option value="{{ $period }}" @selected($days === $period)>Last {{ $period }} days</option>@endforeach
        </select><noscript><button type="submit" class="btn">Apply</button></noscript>
    </form>
</div>
@if($outboundPaused || $registrationPaused)
<div class="alert" role="alert"><strong>Platform safety controls:</strong>
    {{ $outboundPaused ? 'Outgoing messages paused.' : '' }}
    {{ $registrationPaused ? 'New registrations paused.' : '' }}
    <a href="{{ route('platform.settings.index') }}">Manage settings</a>
</div>
@endif
<div class="ws-metric-grid">
    @foreach([
        ['label'=>'All merchants','value'=>$merchants,'detail'=>number_format($merchantsNew).' joined in '.$days.' days'],
        ['label'=>'Active merchants','value'=>$activeMerchants,'detail'=>'Active accounts'],
        ['label'=>'Trial evaluations','value'=>$trialing,'detail'=>'Current trials'],
        ['label'=>'Paid-tier entitlements','value'=>$activeSubscriptions,'detail'=>'Not verified revenue'],
        ['label'=>'Orders recorded','value'=>$orders30d,'detail'=>'Last '.$days.' days'],
        ['label'=>'Inbound events','value'=>$inbound30d,'detail'=>'Last '.$days.' days'],
        ['label'=>'Outbound queued','value'=>$outbound30d,'detail'=>'Last '.$days.' days'],
        ['label'=>'Failed / uncertain outgoing','value'=>$outboundFailed30d,'detail'=>'Last '.$days.' days']
    ] as $stat)
        <div class="card ws-metric"><span class="ws-stat-label">{{ $stat['label'] }}</span>
            <strong class="ws-metric-value">{{ number_format($stat['value']) }}</strong>
            <small class="muted">{{ $stat['detail'] }}</small>
        </div>
    @endforeach
</div>
<div class="ws-dashboard-columns">
    <section class="card">
        <div class="actions"><h2>Outbound messaging trend</h2><span class="pill">{{ number_format($outbound30d) }} queued</span></div>
        <p class="muted">Daily queued outbound messages · UTC · Last {{ $days }} days</p>
        @if($outboundTrend->isNotEmpty())
            @php($maxOutbound = max(1, (int) $outboundTrend->max('total')))
            <div class="ws-spark-bars" role="img" aria-label="Outbound messages recorded by date">
                @foreach($outboundTrend as $day)
                    <div class="ws-bar-wrap" title="{{ $day->day }}: {{ $day->total }} queued">
                        <span class="ws-spark-bar" style="height:{{ max(3, round($day->total * 100 / $maxOutbound)) }}%"></span>
                    </div>
                @endforeach
            </div>
            <div class="ws-chart-ends"><span>{{ $outboundTrend->first()->day }}</span><span>{{ $outboundTrend->last()->day }}</span></div>
            <details class="ws-chart-details"><summary>View message counts by date</summary>
                <div class="table-wrap"><table><thead><tr><th>Date</th><th>Queued</th></tr></thead><tbody>
                @foreach($outboundTrend as $day)<tr><td>{{ $day->day }}</td><td>{{ number_format($day->total) }}</td></tr>@endforeach
                </tbody></table></div>
            </details>
        @else <p class="ws-empty">No outgoing message records in this period.</p> @endif
    </section>
    <section class="card">
        <div class="actions"><h2>Subscription breakdown</h2><a href="{{ route('platform.subscriptions.index') }}">Manage</a></div>
        <div class="ws-summary-list">
            @forelse($subscriptionBreakdown as $status)
                <div><span>{{ ucfirst($status->status) }}</span><strong>{{ number_format($status->total) }}</strong></div>
            @empty <p class="muted">No subscriptions found.</p> @endforelse
            <div><span>Expiring within 7 days</span><strong>{{ number_format($expiringSoon) }}</strong></div>
            <div><span>Plan requests in {{ $days }} days</span><strong>{{ number_format($pendingRequests) }}</strong></div>
            <div><span>Invoice records</span><strong>{{ number_format($subscriptionInvoices) }}</strong></div>
        </div>
        <p class="muted">Automatic subscriptions, refunds and payouts are not connected. Manual entitlements do not count as revenue.</p>
    </section>
</div>
<div class="ws-dashboard-columns">
    <section class="card">
        <div class="actions"><h2>Recent merchants</h2><a href="{{ route('platform.merchants.index') }}">View directory &rarr;</a></div>
        <div class="table-wrap"><table><thead><tr><th>Merchant</th><th>Status</th><th>Plan</th><th>Orders</th><th>Channels</th></tr></thead><tbody>
        @forelse($recentMerchants as $merchant)
            <tr><td><a href="{{ route('platform.merchants.show', $merchant) }}">{{ $merchant->name }}</a>
                <small class="muted"> · {{ $merchant->slug }}</small></td>
                <td><span class="pill {{ $merchant->status !== 'active' ? 'off' : '' }}">{{ ucfirst($merchant->status) }}</span></td>
                <td>{{ $merchant->subscription?->plan?->name ?? 'Unassigned' }}</td>
                <td>{{ $merchant->orders_count }}</td><td>{{ $merchant->messaging_channels_count }}</td></tr>
        @empty <tr><td colspan="5">No merchants yet.</td></tr> @endforelse
        </tbody></table></div>
    </section>
    <section class="card">
        <h2>Messaging & queue health</h2>
        <div class="ws-summary-list">
            <div><span>WhatsApp channels</span><strong>{{ number_format($channels) }}</strong></div>
            <div><span>Last recorded connected</span><strong>{{ number_format($connectedChannels) }}</strong></div>
            <div><span>Pending / sending / uncertain</span><strong>{{ number_format($unresolvedOutbound) }}</strong></div>
            <div><span>Laravel queued jobs</span><strong>{{ number_format($pendingJobs) }}</strong></div>
            <div><span>Laravel failed jobs</span><strong>{{ number_format($failedJobs) }}</strong></div>
        </div>
        <p class="muted">Last inbound webhook event: {{ $lastInbound ?? 'No events' }}</p>
        <p class="muted">Connection status is last recorded, not a live provider probe. Counts do not guarantee delivery.</p>
    </section>
</div>
<section class="card" style="margin-top:20px">
    <div class="actions"><h2>Recent operator activity</h2><a href="{{ route('platform.audit.index') }}">Full audit log</a></div>
    @forelse($recentActivity as $event)
        <p><strong>{{ str_replace('_', ' ', $event->action) }}</strong> · {{ $event->created_at->format('Y-m-d H:i') }} UTC
        @if($event->merchant_id) · Merchant #{{ $event->merchant_id }} @endif</p>
    @empty <p class="muted">No administrator actions recorded.</p> @endforelse
</section>
@endsection
