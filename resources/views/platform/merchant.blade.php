@extends('layouts.app')
@section('title', 'Merchant overview · '.$merchant->name)
@section('content')
<div class="ws-page-intro">
    <div>
        <a class="ws-link-action" href="{{ route('platform.merchants.index') }}">&larr; Merchant directory</a>
        <span class="ws-eyebrow" style="margin-top:15px">MERCHANT 360°</span>
        <h1>{{ $merchant->name }} <span @class(['ws-status-chip', 'suspended' => $merchant->status !== 'active'])>{{ ucfirst($merchant->status) }}</span></h1>
        <p>{{ $merchant->slug }} · {{ ucfirst($merchant->business_type) }} · {{ strtoupper($merchant->currency) }} · {{ $merchant->timezone }}</p>
        <p class="muted">Operational metadata only. Administrator access does not expose decrypted customer messages, private notes, or connection credentials.</p>
    </div>
</div>
<section class="ws-stat-grid" aria-label="Merchant overview statistics">
    @foreach([
        ['label' => 'Products', 'value' => $productCount],
        ['label' => 'Customers', 'value' => $customerCount],
        ['label' => 'Conversations', 'value' => $conversationCount],
        ['label' => 'Orders', 'value' => $orderCount],
        ['label' => 'Inbound · 30d', 'value' => $inbound30d],
        ['label' => 'Outbound queued · month', 'value' => $monthlyMessages],
    ] as $item)
        <div class="ws-stat-card"><small>{{ $item['label'] }}</small><strong>{{ number_format($item['value']) }}</strong></div>
    @endforeach
</section>
<div class="grid" style="align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr))">
    <section class="card">
        <div class="ws-panel-heading"><div><h2>Merchant profile</h2><p>Account identity and operating information.</p></div></div>
        <dl class="ws-summary-list">
            <div><dt>Business</dt><dd>{{ $merchant->name }}</dd></div>
            <div><dt>Merchant ID</dt><dd>#{{ $merchant->id }}</dd></div>
            <div><dt>Created</dt><dd>{{ $merchant->created_at?->format('Y-m-d H:i') ?? '—' }} UTC</dd></div>
            <div><dt>Currency</dt><dd>{{ $merchant->currency }}</dd></div>
            <div><dt>Timezone</dt><dd>{{ $merchant->timezone }}</dd></div>
            <div><dt>Last order</dt><dd>{{ $lastOrder ?? 'None' }}</dd></div>
            <div><dt>Last inbound event</dt><dd>{{ $lastInbound ?? 'None' }}</dd></div>
        </dl>
        <h3 class="ws-section-title">Business team</h3>
        <div class="table-wrap"><table>
            <thead><tr><th>Member</th><th>Access</th></tr></thead>
            <tbody>@forelse($team as $member)
                <tr><td>{{ $member->name }}<small class="ws-row-subtitle">{{ $member->email }}</small></td><td>{{ ucfirst($member->pivot->role) }}</td></tr>
            @empty <tr><td colspan="2">No team members assigned.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
    <section class="card">
        <div class="ws-panel-heading"><div><h2>Subscription and channels</h2><p>Actual entitlements and last recorded messaging states.</p></div></div>
        <dl class="ws-summary-list">
            <div><dt>Current plan</dt><dd>{{ $merchant->subscription?->plan?->name ?? 'No plan' }}</dd></div>
            <div><dt>Subscription state</dt><dd>{{ ucfirst($merchant->subscription?->status ?? 'Missing') }}</dd></div>
            <div><dt>Subscription ends</dt><dd>{{ $merchant->subscription?->period_end?->format('Y-m-d H:i') ?? '—' }} UTC</dd></div>
        </dl>
        <h3 class="ws-section-title">Channel status</h3>
        <dl class="ws-summary-list">
            @forelse($channelCounts as $status => $count)
                <div><dt>{{ ucfirst($status) }} (last recorded)</dt><dd>{{ number_format($count) }}</dd></div>
            @empty <div><dt>WhatsApp channels</dt><dd>No channels yet</dd></div> @endforelse
        </dl>
        <h3 class="ws-section-title">Outbound state · last 30 days</h3>
        <dl class="ws-summary-list">
            @forelse($outboundStates as $status => $count)
                <div><dt>{{ ucfirst($status) }}</dt><dd>{{ number_format($count) }}</dd></div>
            @empty <div><dt>Outgoing records</dt><dd>No activity</dd></div> @endforelse
        </dl>
        <p class="muted" style="font-size:12px;margin-top:15px">The connection indicator is not a live WhatsApp health probe. Queued messages are not confirmed delivery.</p>
    </section>
</div>
<div class="grid" style="margin-top:18px;align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr))">
    <section class="card">
        <h2>Subscription activity</h2>
        @forelse($events as $event)
            <div class="catalog-line"><div><strong>{{ ucfirst(str_replace('_', ' ', $event->event)) }}</strong><small class="ws-row-subtitle">{{ $event->created_at->format('Y-m-d H:i') }} UTC</small></div></div>
        @empty <p class="muted">No subscription activity recorded.</p> @endforelse
        <h3 class="ws-section-title">SaaS invoice entries</h3>
        @forelse($invoices as $invoice)
            <div class="catalog-line"><div><strong>{{ $invoice->public_id }}</strong><small class="ws-row-subtitle">{{ ucfirst($invoice->status) }} · {{ \App\Support\Money::format($invoice->amount_minor, $invoice->currency) }}</small></div></div>
        @empty <p class="muted">No SaaS invoices recorded.</p> @endforelse
        <p class="muted" style="font-size:12px">Manually granted entitlements are not evidence of payment.</p>
        <h3 class="ws-section-title">Platform audit history</h3>
        @forelse($audit as $event)
            <div class="catalog-line"><div><strong>{{ ucfirst(str_replace('_', ' ', $event->action)) }}</strong><small class="ws-row-subtitle">{{ $event->created_at->format('Y-m-d H:i') }} UTC · {{ $event->reason ?: 'No reason recorded' }}</small></div></div>
        @empty <p class="muted">No platform actions recorded.</p> @endforelse
    </section>
    <section class="card">
        <div class="ws-panel-heading"><div><h2>Operator controls</h2><p>Sensitive actions require password, explicit confirmation and an audited reason.</p></div></div>
        <h3>{{ $merchant->status === 'active' ? 'Suspend merchant' : 'Reactivate merchant' }}</h3>
        <p class="muted">Suspension blocks merchant access and new outgoing messages; existing customer and business records remain stored.</p>
<form method="post" action="{{ route('platform.merchants.status', $merchant) }}">
@csrf
<input type="hidden" name="status" value="{{ $merchant->status === 'active' ? 'suspended' : 'active' }}">
<div class="field"><label for="status_reason">Reason (audited)</label><textarea id="status_reason" name="reason" minlength="10" maxlength="250" rows="3" required></textarea></div>
<div class="field"><label for="status_password">Confirm account password</label><input id="status_password" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="field"><label for="confirmation">Type {{ $merchant->status === 'active' ? 'SUSPEND' : 'REACTIVATE' }}</label><input id="confirmation" name="confirmation" required></div>
<button class="btn btn-light" type="submit">Confirm status change</button>
</form>
        <h3 class="ws-section-title">Grant subscription entitlement</h3>
        <p class="muted">Manual grant only: no Stripe charge, receipt, invoice payment, or automatic renewal will be created.</p>
<form method="post" action="{{ route('platform.subscriptions.grant', $merchant) }}">
@csrf
<div class="field"><label for="plan_code">Plan</label><select id="plan_code" name="plan_code"><option value="starter">Starter</option><option value="growth">Growth</option></select></div>
<div class="field"><label for="months">Duration in months (1–24)</label><input id="months" name="months" type="number" min="1" max="24" value="1" required></div>
<div class="field"><label for="grant_reason">Reason (audited)</label><textarea id="grant_reason" name="reason" minlength="10" maxlength="250" rows="3" required></textarea></div>
<div class="field"><label for="grant_password">Confirm account password</label><input id="grant_password" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="field"><label for="grant_confirmation">Type GRANT</label><input id="grant_confirmation" name="confirmation" required></div>
<button class="btn btn-light" type="submit">Grant entitlement — no payment</button>
</form>
    </section>
</div>
@endsection
