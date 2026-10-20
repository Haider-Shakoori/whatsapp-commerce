@extends('layouts.app')
@section('title', 'WhatsApp connections')
@section('content')
<a href="{{ route('merchant.products.index', $merchant) }}">&larr; {{ $merchant->name }} products</a>
<h1 style="margin-top:14px">WhatsApp connections</h1>
<p class="muted">Connect an existing RelayWA session. Commerce continues working even when a messaging session is offline. No messages are sent from this screen.</p>
@foreach($channels as $channel)
    <div class="card">
        <h2>{{ $channel->display_name }}</h2>
        <p><span class="pill {{ $channel->status === 'connected' ? '' : 'off' }}">{{ $channel->status }}</span>
        <span class="muted">· Last checked: {{ $channel->status_checked_at?->format('Y-m-d H:i') ?? 'Never' }} UTC</span></p>
        <p><strong>RelayWA session:</strong> {{ $channel->external_account_id }}</p>
        <p><strong>Webhook URL:</strong><br><code style="overflow-wrap:anywhere">{{ route('api.relaywa.webhook', ['publicId' => $channel->public_id]) }}</code></p>
        <p class="muted">Create a RelayWA webhook endpoint using this HTTPS URL. Use the same secret you entered during setup and subscribe to <code>message.received</code> and relevant session events. The webhook secret is not displayed again.</p>
        <form method="post" action="{{ route('merchant.channels.destroy', [$merchant, $channel]) }}">
            @csrf @method('DELETE')
            <button class="btn btn-light" type="submit">Detach channel</button>
        </form>
    </div>
@endforeach
<div class="card narrow" style="margin-left:0">
    <h2>Connect RelayWA session</h2>
    <p class="muted">Create a session-specific API key in RelayWA with <code>sessions.read</code> and <code>messages.send</code> scopes. Create a webhook endpoint in RelayWA after this step; enter its secret here. Credentials are encrypted at rest.</p>
    <form method="post" action="{{ route('merchant.channels.store', $merchant) }}">
        @csrf
        <div class="field"><label for="display_name">Connection label</label><input id="display_name" name="display_name" value="{{ old('display_name') }}" placeholder="Restaurant WhatsApp" required></div>
        <div class="field"><label for="session_id">RelayWA session UUID</label><input id="session_id" name="session_id" value="{{ old('session_id') }}" required></div>
        <div class="field"><label for="api_key">Session-scoped API key</label><input id="api_key" name="api_key" type="password" autocomplete="new-password" required></div>
        <div class="field"><label for="webhook_secret">Webhook signing secret (copy from RelayWA)</label><input id="webhook_secret" name="webhook_secret" type="password" autocomplete="new-password" required></div>
        <button class="btn" type="submit">Verify and connect</button>
    </form>
</div>
@endsection
