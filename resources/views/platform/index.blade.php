@extends('layouts.app')
@section('title', 'Platform administration')
@section('content')
<div class="ws-page-intro">
    <div>
        <span class="ws-eyebrow">PLATFORM OPERATIONS</span>
        <h1>Platform overview</h1>
        <p>Merchant activity, operational health and messaging usage. All metrics are recorded activity, not verified sales or subscription revenue.</p>
    </div>
    <form method="get" action="{{ route('platform.index') }}">
        <label for="days">Reporting period (UTC)</label>
        <select id="days" name="days" onchange="this.form.submit()">
            @foreach([7, 30, 90] as $period)
                <option value="{{ $period }}" @selected($selectedDays === $period)>Last {{ $period }} days</option>
            @endforeach
        </select>
        <noscript><button class="btn" type="submit">Apply</button></noscript>
    </form>
</div>
@if($outboundPaused || $registrationPaused)
    <div class="alert" role="status">
        <strong>Platform safeguards active:</strong>
        @if($outboundPaused) Outbound messages paused. @endif
        @if($registrationPaused) New merchant registration paused. @endif
        <a href="{{ route('platform.settings.index') }}">Review settings</a>
    </div>
@endif
<section class="ws-stat-grid" aria-label="Platform statistics">
    @foreach([
        ['label' => 'All merchants', 'value' => $merchants],
        ['label' => 'Active merchants', 'value' => $activeMerchants],
        ['label' => 'New merchants · '.$selectedDays.'d', 'value' => $newMerchantsInPeriod],
        ['label' => 'Current trials', 'value' => $trialing],
        ['label' => 'Active plan entitlements', 'value' => $activeSubscriptions],
        ['label' => 'Expiring in 7 days', 'value' => $expiringSoon],
        ['label' => 'WhatsApp channels', 'value' => $channels],
        ['label' => 'Connected · last recorded', 'value' => $connectedChannels],
    ] as $stat)
        <div class="ws-stat-card">
            <small>{{ $stat['label'] }}</small>
            <strong>{{ number_format($stat['value']) }}</strong>
        </div>
    @endforeach
</section>
<div class="grid" style="align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,370px),1fr))">
    <section class="card" aria-labelledby="usage-chart-title">
        <div class="ws-panel-heading">
            <div><h2 id="usage-chart-title">Messaging & orders activity</h2><p>Daily records during the selected {{ $selectedDays }}-day period, UTC.</p></div>
        </div>
        <div class="ws-trend-chart" style="--chart-days:{{ count($trend) }}" role="img" aria-label="Daily inbound messages, queued outbound records and created orders from {{ $trend[0]['day'] }} through {{ $trend[count($trend)-1]['day'] }}">
            @foreach($trend as $day)
                <div class="ws-trend-day" title="{{ $day['day'] }} · Outbound: {{ $day['outbound'] }}, Inbound: {{ $day['inbound'] }}, Orders: {{ $day['orders'] }}">
                    <span class="ws-trend-bar" style="--bar-height:{{ max(2, round($day['outbound'] * 100 / $trendMax)) }}%"></span>
                    <span class="ws-trend-bar is-secondary" style="--bar-height:{{ max(2, round($day['inbound'] * 100 / $trendMax)) }}%"></span>
                    <span class="ws-trend-bar is-tertiary" style="--bar-height:{{ max(2, round($day['orders'] * 100 / $trendMax)) }}%"></span>
                </div>
            @endforeach
        </div>
        <div class="ws-trend-legend"><span><i></i> Queued outbound</span><span><i class="blue"></i> Incoming events</span><span><i class="gold"></i> Orders</span></div>
        <div class="ws-trend-axis"><span>{{ $trend[0]['day'] }}</span><span>Highest daily count: {{ number_format($trendMax) }}</span><span>{{ $trend[count($trend)-1]['day'] }}</span></div>
        <p class="muted" style="font-size:12px;margin-top:17px">Outbound records are not proof of WhatsApp delivery. Orders created are not payment receipts.</p>
    </section>
    <section class="card" aria-labelledby="system-status-title">
        <div class="ws-panel-heading"><div><h2 id="system-status-title">Operations health</h2><p>Database records and last reported states, not live external-service uptime.</p></div></div>
        <dl class="ws-summary-list">
            <div><dt>Pending jobs</dt><dd>{{ number_format($pendingJobs) }}</dd></div>
            <div><dt>Failed jobs</dt><dd>{{ number_format($failedJobs) }}</dd></div>
            <div><dt>Queued / sending / uncertain</dt><dd>{{ number_format($unresolvedOutbound) }}</dd></div>
            <div><dt>Failed / uncertain outgoing · 30d</dt><dd>{{ number_format($outboundFailed30d) }}</dd></div>
            <div><dt>Incoming events · 30d</dt><dd>{{ number_format($inbound30d) }}</dd></div>
            <div><dt>Outbound records · 30d</dt><dd>{{ number_format($outbound30d) }}</dd></div>
            <div><dt>Orders created · 30d</dt><dd>{{ number_format($orders30d) }}</dd></div>
            <div><dt>Last inbound event</dt><dd>{{ $lastInbound ?? 'None recorded' }}</dd></div>
        </dl>
        <p class="muted" style="font-size:12px">All totals shown as 30d remain 30-day comparisons regardless of selected chart range.</p>
    </section>
</div>
<div class="grid" style="margin-top:18px;align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,370px),1fr))">
    <section class="card" aria-labelledby="recent-merchants-title">
        <div class="ws-panel-heading">
            <div><h2 id="recent-merchants-title">Recently onboarded merchants</h2><p>Quick access to tenant operational profiles.</p></div>
            <a href="{{ route('platform.merchants.index') }}" class="ws-link-action">View all &rarr;</a>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>Business</th><th>Plan</th><th>Status</th><th>Orders</th></tr></thead>
            <tbody>
                @forelse($recentMerchants as $merchant)
                    <tr>
                        <td><a href="{{ route('platform.merchants.show', $merchant) }}" class="ws-row-title">{{ $merchant->name }}</a><small class="ws-row-subtitle">{{ $merchant->slug }}</small></td>
                        <td>{{ $merchant->subscription?->plan?->name ?? 'None' }}</td>
                        <td><span @class(['ws-status-chip', 'suspended' => $merchant->status !== 'active'])>{{ ucfirst($merchant->status) }}</span></td>
                        <td>{{ number_format($merchant->orders_count) }}</td>
                    </tr>
                @empty <tr><td colspan="4">No merchants yet.</td></tr> @endforelse
            </tbody>
        </table></div>
    </section>
    <section class="card" aria-labelledby="admin-activity-title">
        <div class="ws-panel-heading"><div><h2 id="admin-activity-title">Administrator activity</h2><p>Recorded platform actions, never customer message content.</p></div><a href="{{ route('platform.audit.index') }}" class="ws-link-action">Audit trail &rarr;</a></div>
        @forelse($recentActivity as $event)
            <div class="catalog-line"><div><strong>{{ ucfirst(str_replace('_', ' ', $event->action)) }}</strong>
                <small class="ws-row-subtitle">{{ $event->created_at->format('Y-m-d H:i') }} UTC @if($event->merchant_id) · Merchant #{{ $event->merchant_id }} @endif</small></div></div>
        @empty <p class="muted">No operator actions recorded.</p> @endforelse
        <div class="footnote">Recorded SaaS invoices: {{ $subscriptionInvoices }}. Subscription payments are not integrated; manual entitlements are not revenue. Plan requests in 30d: {{ $pendingRequests }}.</div>
    </section>
</div>
@endsection
