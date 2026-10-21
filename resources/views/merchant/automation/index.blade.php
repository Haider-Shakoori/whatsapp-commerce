@extends('layouts.app')
@section('title', 'Automation · '.$merchant->name)
@section('content')
<a href="{{ route('merchant.inbox.index', $merchant) }}">&larr; Team inbox</a>
<div class="actions" style="margin:16px 0"><div>
<h1>Automation & AI assistant</h1>
<p class="muted">All messaging automation is opt-in and controlled by this merchant. The platform transport remains disabled by default.</p>
</div><span class="pill {{ $settings->enabled ? '' : 'off' }}">{{ $settings->enabled ? 'Enabled' : 'Disabled' }}</span></div>
<div class="grid" style="align-items:start">
<div class="card">
  <h2>Automation settings</h2>
  <form method="post" action="{{ route('merchant.automation.update', $merchant) }}">
    @csrf @method('PUT')
    @foreach([
      'enabled' => 'Enable this merchant’s automations',
      'after_hours_enabled' => 'Reply outside business hours',
      'keyword_enabled' => 'Enable keyword replies and routing',
      'followups_enabled' => 'Allow staff-scheduled follow-ups',
      'ai_drafts_enabled' => 'Allow staff-requested AI reply drafts (never auto-send)',
    ] as $field => $title)
      <input type="hidden" name="{{ $field }}" value="0">
      <div class="field"><label><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $settings->$field ? '1' : '0') == '1')> {{ $title }}</label></div>
    @endforeach

    <h3>Business hours ({{ $merchant->timezone }})</h3>
    <p class="muted">Active days use local time. Unchecked days are closed. Overnight shifts are not supported yet. A blank schedule disables after-hours detection.</p>
    @php($days = [1 => 'Monday',2 => 'Tuesday',3 => 'Wednesday',4 => 'Thursday',5 => 'Friday',6 => 'Saturday',7 => 'Sunday'])
    @foreach($days as $number => $label)
      @php($value = $settings->weekly_hours[(string) $number] ?? null)
      <div class="actions" style="margin-bottom:12px;align-items:end">
        <div class="field" style="min-width:115px"><label>
          <input type="hidden" name="hours[{{ $number }}][enabled]" value="0">
          <input type="checkbox" name="hours[{{ $number }}][enabled]" value="1" @checked(old('hours.'.$number.'.enabled', $value ? '1' : '0') == '1')> {{ $label }}
        </label></div>
        <div class="field"><label>Opens</label><input type="time" name="hours[{{ $number }}][start]" value="{{ old('hours.'.$number.'.start', $value['start'] ?? '09:00') }}"></div>
        <div class="field"><label>Closes</label><input type="time" name="hours[{{ $number }}][end]" value="{{ old('hours.'.$number.'.end', $value['end'] ?? '17:00') }}"></div>
      </div>
    @endforeach

    <div class="field"><label for="after_hours_message">After-hours response</label>
      <textarea id="after_hours_message" name="after_hours_message" maxlength="1000" rows="3" placeholder="Thanks for contacting us. Our team will reply during business hours.">{{ old('after_hours_message', $settings->after_hours_message) }}</textarea>
    </div>
    <div class="field"><label for="ai_guidance">Optional AI drafting guidance</label>
      <textarea id="ai_guidance" name="ai_guidance" maxlength="600" rows="3" placeholder="Our business tone, common services and when to hand off to staff.">{{ old('ai_guidance', $settings->ai_guidance) }}</textarea>
    </div>
    <div class="field"><label><input type="checkbox" name="ai_consent" value="1"> I understand that requesting an AI draft sends the selected customer text and merchant guidance to an external AI service. Staff must review before sending.</label></div>
    <button class="btn" type="submit">Save automation settings</button>
  </form>
</div>
<div class="card">
  <h2>Keyword rules & team routing</h2>
  <p class="muted">Up to 30 rules. The first matching keyword wins, ordered by priority. Optional assignment goes only to a member of your merchant team. Replies are limited to once per customer every 30 minutes.</p>
  <form method="post" action="{{ route('merchant.automation.rules.store', $merchant) }}">
    @csrf
    <div class="field"><label for="keyword">Keyword or phrase</label><input id="keyword" name="keyword" required maxlength="80" placeholder="Opening hours, shipping, refund"></div>
    <div class="field"><label for="reply_text">Automated reply (optional if routing)</label><textarea id="reply_text" name="reply_text" maxlength="1000" rows="3"></textarea></div>
    <div class="field"><label for="assign_to">Assign matching conversation</label>
      <select id="assign_to" name="assign_to"><option value="">Do not assign</option>
        @foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
      </select>
    </div>
    <div class="field"><label for="priority">Priority (lower is earlier)</label><input id="priority" name="priority" type="number" min="1" max="1000" value="100"></div>
    <button class="btn" type="submit">Add rule</button>
  </form>
  <div style="margin-top:20px"><h3>Configured rules</h3>
  @forelse($rules as $rule)
    <div style="padding:12px 0;border-top:1px solid #e5e7eb">
      <div class="actions"><strong>{{ $rule->keyword }}</strong><small class="muted">Priority {{ $rule->priority }}</small></div>
      <p style="white-space:pre-wrap">{{ $rule->reply_text ?: 'Route only (no auto-reply)' }}</p>
      <p class="muted">Assigned member ID: {{ $rule->assign_to ?: 'Not set' }}</p>
      <form method="post" action="{{ route('merchant.automation.rules.destroy', [$merchant, $rule]) }}">@csrf @method('DELETE')<button class="btn btn-light" type="submit">Remove rule</button></form>
    </div>
  @empty<p class="muted">No keyword rules yet.</p>@endforelse
  </div>
</div>
</div>
@endsection
