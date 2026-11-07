@extends('layouts.app')
@section('title', 'Platform audit trail')
@section('content')
<h1>Platform audit trail</h1>
@include('platform.partials.nav')
<p class="muted">Append-only operator action history. Secret keys and customer message content are never intentionally recorded here.</p>
<div class="card"><form class="actions" method="get" action="{{ route('platform.audit.index') }}">
<div class="field"><label for="action">Action</label><input id="action" name="action" maxlength="80" value="{{ request('action') }}"></div>
<button class="btn" type="submit">Filter</button></form>
<div class="table-wrap"><table><thead><tr><th>When (UTC)</th><th>Actor ID</th><th>Action</th><th>Merchant ID</th><th>Reason</th><th>Changes</th></tr></thead><tbody>
@forelse($logs as $log)
<tr><td>{{ $log->created_at->format('Y-m-d H:i') }}</td>
<td>{{ $log->actor_user_id ?? 'Console operator' }}</td><td>{{ $log->action }}</td>
<td>{{ $log->merchant_id ?? '—' }}</td><td>{{ $log->reason ?? '—' }}</td>
<td><code>{{ json_encode($log->changes) }}</code></td></tr>
@empty<tr><td colspan="6">No audit records.</td></tr>@endforelse
</tbody></table></div>{{ $logs->links() }}</div>
@endsection
