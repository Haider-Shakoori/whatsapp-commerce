@extends('layouts.app')
@section('title', 'Merchant overview · '.$merchant->name)
@section('content')
<div class="ws-page-intro">
    <div>
        <a href="{{ route('platform.merchants.index') }}">&larr; Merchant directory</a>
        <span class="ws-eyebrow" style="margin-top:13px">MERCHANT 360° PROFILE</span>
        <h1>{{ $merchant->name }}</h1>
        <p>{{ ucfirst($merchant->business_type) }} · {{ $merchant->currency }} · {{ $merchant->timezone }}</p>
    </div>
    <span class="pill {{ $merchant->status !== 'active' ? 'off' : '' }}">{{ ucfirst($merchant->status) }}</span>
</div>
<div class="ws-metric-grid" style="margin-bottom:20px">
    @foreach([
        ['label' => 'Products', 'value' => $productCount],
        ['label' => 'Customers', 'value' => $customerCount],
        ['label' => 'Recorded orders', 'value' => $orderCount],
        ['label' => 'WhatsApp channels', 'value' => $channelCount],
    ] as $stat)
        <div class="card ws-metric"><span class="ws-stat-label">{{ $stat['label'] }}</span><strong class="ws-metric-value">{{ number_format($stat['value']) }}</strong></div>
    @endforeach
</div>
<p class="muted">This administrative view contains operational metadata only. Private customer chats, API credentials and customer notes are not displayed.</p>
<div class="grid" style="align-items:start">
<div class="card">
<h2>Team members</h2>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead><tbody>
@forelse($team as $member)<tr><td>{{ $member->name }}</td><td>{{ $member->email }}</td><td>{{ ucfirst($member->pivot->role) }}</td></tr>
@empty<tr><td colspan="3">No team members.</td></tr>@endforelse
</tbody></table></div>
<h2 style="margin-top:20px">Subscription</h2>
<p><strong>{{ $merchant->subscription?->plan?->name ?? 'No plan' }}</strong> · {{ $merchant->subscription?->status ?? 'No status' }}</p>
<p>Expires: {{ $merchant->subscription?->period_end?->format('Y-m-d H:i') ?? '—' }} UTC</p>
<p>Current month outbound queued: {{ $monthlyMessages }}</p>
<p>Orders recorded: {{ $orderCount }}</p>
<h3>Recent orders</h3>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Status</th><th>Created (UTC)</th></tr></thead><tbody>
@forelse($recentOrders as $order)
<tr><td>#{{ $order->id }}</td><td>{{ ucfirst($order->status) }}</td><td>{{ $order->created_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
@empty <tr><td colspan="3">No orders recorded.</td></tr> @endforelse
</tbody></table></div>
<h3 style="margin-top:20px">Channel status</h3>
@forelse($channelCounts as $status => $count)<p>{{ $status }} · {{ $count }}</p>@empty<p>No channels</p>@endforelse
<h3 style="margin-top:20px">Outbound state · last 30 days</h3>
@forelse($outboundStates as $status => $count)<p>{{ $status }} · {{ $count }}</p>@empty<p>No outbound messages</p>@endforelse
<h3 style="margin-top:20px">Recent subscription events</h3>
@forelse($events as $event)
<p>{{ str_replace('_', ' ', $event->event) }} · {{ $event->created_at->format('Y-m-d') }}</p>
@empty<p>No events.</p>@endforelse
<h3 style="margin-top:20px">SaaS invoices</h3>
@forelse($invoices as $invoice)
<p>{{ $invoice->public_id }} · {{ $invoice->status }} · {{ $invoice->currency }} {{ $invoice->amount_minor }} minor units</p>
@empty<p>No SaaS invoices.</p>@endforelse
</div>
<div class="card">
<h2>{{ $merchant->status === 'active' ? 'Suspend merchant' : 'Reactivate merchant' }}</h2>
<p class="muted">Suspension disables merchant workspace access and new outgoing messages while preserving data. Reinstatement does not bypass an expired subscription or global messaging pause.</p>
<form method="post" action="{{ route('platform.merchants.status', $merchant) }}">
@csrf
<input type="hidden" name="status" value="{{ $merchant->status === 'active' ? 'suspended' : 'active' }}">
<div class="field"><label for="status_reason">Reason (audited)</label><textarea id="status_reason" name="reason" minlength="10" maxlength="250" rows="3" required></textarea></div>
<div class="field"><label for="status_password">Confirm account password</label><input id="status_password" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="field"><label for="confirmation">Type {{ $merchant->status === 'active' ? 'SUSPEND' : 'REACTIVATE' }}</label><input id="confirmation" name="confirmation" required></div>
<button class="btn btn-light" type="submit">Confirm status change</button>
</form>
<h2 style="margin-top:26px">Manual subscription entitlement</h2>
<p class="muted">Grant or extend a plan without taking payment. Only use following documented operator approval. This creates an audit event but NO paid invoice, charge or receipt.</p>
<form method="post" action="{{ route('platform.subscriptions.grant', $merchant) }}">
@csrf
<div class="field"><label for="plan_code">Plan</label><select id="plan_code" name="plan_code"><option value="starter">Starter</option><option value="growth">Growth</option></select></div>
<div class="field"><label for="months">Duration in months (1–24)</label><input id="months" name="months" type="number" min="1" max="24" value="1" required></div>
<div class="field"><label for="grant_reason">Reason (audited)</label><textarea id="grant_reason" name="reason" minlength="10" maxlength="250" rows="3" required></textarea></div>
<div class="field"><label for="grant_password">Confirm account password</label><input id="grant_password" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="field"><label for="grant_confirmation">Type GRANT</label><input id="grant_confirmation" name="confirmation" required></div>
<button class="btn btn-light" type="submit">Grant entitlement — no payment</button>
</form>
<h2 style="margin-top:26px">Platform audit history</h2>
@forelse($audit as $event)
<p>{{ $event->action }} · {{ $event->created_at->format('Y-m-d H:i') }} UTC · {{ $event->reason ?: 'No reason' }}</p>
@empty<p class="muted">No platform actions.</p>@endforelse
</div></div>
@endsection
