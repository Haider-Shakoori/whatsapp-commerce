<?php

namespace App\Http\Controllers;

use App\Automation\AiDraftAssistant;
use App\Models\AutomationFollowup;
use App\Models\AutomationSetting;
use App\Models\Conversation;
use App\Models\Merchant;
use App\Saas\Exceptions\SubscriptionLimitException;
use App\Saas\MerchantEntitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ConversationAutomationController extends Controller
{
    public function schedule(Request $request, Merchant $merchant, Conversation $conversation, MerchantEntitlements $entitlements): RedirectResponse
    {
        $conversation = $merchant->conversations()->with('customer')
            ->whereKey($conversation->id)->firstOrFail();
        abort_unless($this->canHandle($request, $merchant, $conversation), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
            'delay_hours' => ['required', 'integer', 'between:1,168'],
            'consent_confirmed' => ['required', 'accepted'],
        ]);
        try {
            $entitlements->assertFeature($merchant, 'automation');
        } catch (SubscriptionLimitException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }
        $setting = AutomationSetting::where('merchant_id', $merchant->id)->first();
        if (! $setting?->enabled || ! $setting->followups_enabled
            || $conversation->status !== 'open' || $conversation->customer->automation_opted_out) {
            throw ValidationException::withMessages(['body' => 'Follow-ups are disabled or this conversation cannot receive them.']);
        }
        $last = $conversation->messages()->where('direction', 'inbound')->max('id');
        if (! $last) {
            throw ValidationException::withMessages(['body' => 'A customer-initiated inbound conversation is required.']);
        }
        if ($merchant->automationFollowups()->where('conversation_id', $conversation->id)
            ->where('status', 'scheduled')->exists()) {
            throw ValidationException::withMessages(['body' => 'A follow-up is already scheduled for this conversation.']);
        }

        $merchant->automationFollowups()->create([
            'conversation_id' => $conversation->id,
            'created_by' => $request->user()->id,
            'latest_inbound_id' => $last,
            'body' => $data['body'],
            'due_at' => now()->addHours($data['delay_hours']),
            'status' => 'scheduled',
        ]);

        return back()->with('status', 'One follow-up scheduled, subject to opt-out, new replies and business hours.');
    }

    public function cancel(Request $request, Merchant $merchant, Conversation $conversation, AutomationFollowup $followup): RedirectResponse
    {
        $conversation = $merchant->conversations()->whereKey($conversation->id)->firstOrFail();
        abort_unless($this->canHandle($request, $merchant, $conversation), 403);
        $followup = $merchant->automationFollowups()->where('conversation_id', $conversation->id)
            ->whereKey($followup->id)->firstOrFail();
        if ($followup->status === 'scheduled') {
            $followup->update(['status' => 'cancelled']);
        }

        return back()->with('status', 'Pending follow-up cancelled if not already queued.');
    }

    public function draft(Request $request, Merchant $merchant, Conversation $conversation, AiDraftAssistant $assistant, MerchantEntitlements $entitlements): RedirectResponse
    {
        $conversation = $merchant->conversations()->with('customer')
            ->whereKey($conversation->id)->firstOrFail();
        abort_unless($this->canHandle($request, $merchant, $conversation), 403);

        try {
            $entitlements->assertFeature($merchant, 'ai_drafts');
        } catch (SubscriptionLimitException $e) {
            throw ValidationException::withMessages(['ai' => $e->getMessage()]);
        }
        $setting = AutomationSetting::where('merchant_id', $merchant->id)->first();
        if (! $setting?->ai_drafts_enabled || $conversation->status !== 'open') {
            throw ValidationException::withMessages(['ai' => 'AI drafting is not enabled for this merchant or conversation.']);
        }

        try {
            $draft = $assistant->draft($conversation, (string) $setting->ai_guidance);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['ai' => $e->getMessage()]);
        }

        // Review/edit is mandatory. The draft is never sent or persisted as a message.
        return back()->with('ai_draft', $draft)->with('status', 'AI draft generated. Review it before choosing to send.');
    }

    private function canHandle(Request $request, Merchant $merchant, Conversation $conversation): bool
    {
        return $request->user()->merchants()->whereKey($merchant->id)
            ->wherePivotIn('role', ['owner', 'manager'])->exists()
            || $conversation->assigned_to === null
            || $conversation->assigned_to === $request->user()->id;
    }
}
