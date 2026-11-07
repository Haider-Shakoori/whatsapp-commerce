@extends('layouts.app')
@section('title', 'Customers · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.inbox.index', $merchant) }}">&larr; Team inbox</a>
<h1 style="margin:16px 0">Customers</h1>
<div class="card" style="margin-bottom:18px">
<form method="get" action="{{ route('merchant.customers.index', $merchant) }}" class="actions">
<div class="field" style="flex:1"><label for="search">Find by customer name or phone</label><input id="search" name="search" maxlength="120" value="{{ request('search') }}" placeholder="Name or phone number"></div>
<button class="btn" type="submit">Search</button>
</form></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Customer</th><th>Phone</th><th>Conversations</th></tr></thead>
<tbody>@forelse($customers as $customer)<tr><td><a href="{{ route('merchant.customers.show', [$merchant, $customer]) }}">{{ $customer->name ?: 'Unnamed customer' }}</a></td>
<td>{{ $customer->phone }}</td><td>{{ $customer->conversations_count }}</td></tr>
@empty<tr><td colspan="3">No customers found.</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:14px">{{ $customers->links() }}</div></div>
@endsection
