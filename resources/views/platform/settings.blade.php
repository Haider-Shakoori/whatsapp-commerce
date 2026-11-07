@extends('layouts.app')
@section('title', 'Platform safety settings')
@section('content')
<h1>Platform safety settings</h1>
@include('platform.partials.nav')
<p class="muted">These global switches are emergency operational controls. They do not override the independently disabled RelayWA transport, and changing them never activates payment collection.</p>
<div class="card" style="max-width:740px">
<form method="post" action="{{ route('platform.settings.update') }}">
@csrf @method('PUT')
<input type="hidden" name="registration_paused" value="0">
<div class="field"><label><input type="checkbox" name="registration_paused" value="1" @checked($registrationPaused)> Pause new registrations</label>
<small class="muted">Blocks registration screen and form submission (HTTP 503). Existing logins remain available.</small></div>
<input type="hidden" name="outbound_paused" value="0">
<div class="field"><label><input type="checkbox" name="outbound_paused" value="1" @checked($outboundPaused)> Pause outgoing WhatsApp messages</label>
<small class="muted">Blocks new outgoing queue entries and checks again before pending queue workers send. Does not retract already delivered messages.</small></div>
<div class="field"><label for="reason">Change reason (audited)</label><textarea id="reason" name="reason" minlength="10" maxlength="250" rows="3" required></textarea></div>
<div class="field"><label for="password">Confirm account password</label><input id="password" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="field"><label for="confirmation">Type APPLY to confirm</label><input id="confirmation" name="confirmation" required></div>
<button class="btn" type="submit">Apply safety settings</button>
</form>
</div>
@endsection
