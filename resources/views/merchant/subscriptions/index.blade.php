@extends('layouts.app')
@section('title', 'Subscription & billing · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.products.index', $merchant) }}">&larr; {{ $merchant->name }} workspace</a>
<div class="actions" style="margin:16px 0"><div><h1>Subscription & billing</h1>
<p class="muted">Plan benefits, usage, renewal dates and recorded billing history. No automatic charges or renewals are enabled.</p></div>
<span class="pill {{ $summary['active'] ? '' : 'off' }}">{{ $summary['active'] ? 'Entitled' : 'Inactive / expired' }}</span></div>

<div class="card">
    <h2>Current subscription</h2>
    <p><strong>{{ $summary['plan']?->name ?? 'Unassigned' }}</strong>
    · {{ ucfirst($summary['subscription']?->status ?? 'missing') }}</p>
    <p class="muted">Period: {{ $summary['subscription']?->period_start?->format('Y-m-d H:i') ?? '—' }}
    to {{ $summary['subscription']?->period_end?->format('Y-m-d H:i') ?? '—' }} UTC</p>
    <p class="muted">Automatic renewal: disabled. After expiry, existing data remains visible, but new metered actions and sends are blocked.</p>
</div>

<div class="grid" style="margin:18px 0">
    @foreach([
        ['label' => 'WhatsApp channels', 'used' => $summary['channels'], 'limit' => $summary['plan']?->max_channels],
        ['label' => 'Team members', 'used' => $summary['members'], 'limit' => $summary['plan']?->max_team_members],
        ['label' => 'Catalog products', 'used' => $summary['products'], 'limit' => $summary['plan']?->max_products],
        ['label' => 'Outbound queued ('.$summary['month'].' UTC)', 'used' => $summary['messages'], 'limit' => $summary['plan']?->max_monthly_outbound],
    ] as $item)
    <div class="card"><p class="muted">{{ $item['label'] }}</p>
    <h2>{{ $item['used'] }} / {{ $item['limit'] ?? '—' }}</h2></div>
    @endforeach
</div>

<h2 style="margin:20px 0">Available plans</h2>
<p class="muted">Plan prices have not been approved and are intentionally not shown. A request does not change entitlements, collect money or establish a subscription payment.</p>
<div class="grid">
@foreach($plans as $plan)
<div class="card">
<h3>{{ $plan->name }}</h3><p class="muted">{{ $plan->description }}</p>
<p>Channels: {{ $plan->max_channels }} · Team: {{ $plan->max_team_members }} · Products: {{ $plan->max_products }}</p>
<p>Monthly outbound: {{ number_format($plan->max_monthly_outbound) }}</p>
<p>Automation: {{ $plan->allow_automation ? 'Included' : 'Not included' }} · AI drafts: {{ $plan->allow_ai_drafts ? 'Included' : 'Not included' }}</p>
<p><strong>Price: To be announced</strong></p>
@if($canManage && $plan->code !== 'trial' && $summary['plan']?->id !== $plan->id)
<form method="post" action="{{ route('merchant.subscription.request', $merchant) }}">
@csrf<input type="hidden" name="plan_code" value="{{ $plan->code }}">
<button type="submit" class="btn btn-light">Request {{ $plan->name }}</button>
</form>
@endif
</div>
@endforeach
</div>

<div class="card" style="margin-top:18px">
<h2>Billing history</h2>
<p class="muted">No payment has been collected through the SaaS subscription module. An issued invoice will be displayed here once a verified billing workflow is enabled.</p>
<div class="table-wrap"><table>
<thead><tr><th>Invoice</th><th>Status</th><th>Amount</th><th>Issued</th></tr></thead>
<tbody>
@forelse($invoices as $invoice)
<tr><td>{{ $invoice->public_id }}</td><td>{{ ucfirst($invoice->status) }}</td>
<td>{{ \App\Support\Money::format($invoice->amount_minor, $invoice->currency) }}</td>
<td>{{ $invoice->issued_at?->format('Y-m-d') ?? 'Draft' }}</td></tr>
@empty<tr><td colspan="4" class="muted">No invoices have been issued.</td></tr>@endforelse
</tbody></table></div>
<h3 style="margin-top:24px">Subscription activity</h3>
@forelse($events as $event)
<p>{{ str_replace('_', ' ', ucfirst($event->event)) }} · {{ $event->created_at->format('Y-m-d H:i') }} UTC</p>
@empty<p class="muted">No subscription events yet.</p>@endforelse
</div>
@endsection
