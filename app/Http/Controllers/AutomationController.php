<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\AutomationSetting;
use App\Models\Merchant;
use App\Saas\Exceptions\SubscriptionLimitException;
use App\Saas\MerchantEntitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(Merchant $merchant): View
    {
        $settings = AutomationSetting::firstOrCreate(['merchant_id' => $merchant->id]);

        return view('merchant.automation.index', [
            'merchant' => $merchant,
            'settings' => $settings,
            'rules' => $merchant->automationRules()->orderBy('priority')->orderBy('id')->get(),
            'members' => $merchant->users()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Merchant $merchant, MerchantEntitlements $entitlements): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'after_hours_enabled' => ['required', 'boolean'],
            'keyword_enabled' => ['required', 'boolean'],
            'followups_enabled' => ['required', 'boolean'],
            'ai_drafts_enabled' => ['required', 'boolean'],
            'ai_consent' => ['nullable', 'accepted'],
            'after_hours_message' => ['nullable', 'string', 'max:1000'],
            'ai_guidance' => ['nullable', 'string', 'max:600'],
            'hours' => ['nullable', 'array'],
            'hours.*.enabled' => ['nullable', 'boolean'],
            'hours.*.start' => ['nullable', 'date_format:H:i'],
            'hours.*.end' => ['nullable', 'date_format:H:i'],
        ]);
        $hours = [];
        foreach (range(1, 7) as $day) {
            $range = $data['hours'][$day] ?? [];
            if (! empty($range['enabled'])) {
                $start = $range['start'] ?? null;
                $end = $range['end'] ?? null;
                if (! $start || ! $end || $start >= $end) {
                    throw ValidationException::withMessages([
                        'hours' => 'Active business days require a start time earlier than the closing time.',
                    ]);
                }
                $hours[(string) $day] = ['start' => $start, 'end' => $end];
            }
        }
        if ($data['after_hours_enabled'] && ($hours === [] || empty($data['after_hours_message']))) {
            throw ValidationException::withMessages([
                'after_hours_message' => 'Set working hours and an after-hours message first.',
            ]);
        }
        if ($data['ai_drafts_enabled'] && ! $request->boolean('ai_consent')) {
            throw ValidationException::withMessages([
                'ai_consent' => 'Confirm that merchant-selected customer text may be processed by the AI provider.',
            ]);
        }

        try {
            if ((bool) $data['enabled']) {
                $entitlements->assertFeature($merchant, 'automation');
            }
            if ((bool) $data['ai_drafts_enabled']) {
                $entitlements->assertFeature($merchant, 'ai_drafts');
            }
        } catch (SubscriptionLimitException $e) {
            throw ValidationException::withMessages(['enabled' => $e->getMessage()]);
        }

        AutomationSetting::updateOrCreate(['merchant_id' => $merchant->id], [
            'enabled' => (bool) $data['enabled'],
            'after_hours_enabled' => (bool) $data['after_hours_enabled'],
            'keyword_enabled' => (bool) $data['keyword_enabled'],
            'followups_enabled' => (bool) $data['followups_enabled'],
            'ai_drafts_enabled' => (bool) $data['ai_drafts_enabled'],
            'weekly_hours' => $hours,
            'after_hours_message' => $data['after_hours_message'] ?? null,
            'ai_guidance' => $data['ai_guidance'] ?? null,
        ]);

        return back()->with('status', 'Automation settings saved. Global messaging permissions remain unchanged.');
    }

    public function addRule(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'keyword' => ['required', 'string', 'min:2', 'max:80'],
            'reply_text' => ['nullable', 'string', 'max:1000'],
            'assign_to' => ['nullable', 'integer',
                Rule::exists('merchant_user', 'user_id')->where('merchant_id', $merchant->id)],
            'priority' => ['required', 'integer', 'between:1,1000'],
        ]);
        if (empty($data['reply_text']) && empty($data['assign_to'])) {
            throw ValidationException::withMessages(['reply_text' => 'Provide a reply or an assignment.']);
        }
        if ($merchant->automationRules()->count() >= 30) {
            throw ValidationException::withMessages(['keyword' => 'Maximum 30 rules per merchant.']);
        }
        $merchant->automationRules()->create([
            'keyword' => trim($data['keyword']),
            'reply_text' => $data['reply_text'] ?? null,
            'assign_to' => $data['assign_to'] ?? null,
            'priority' => $data['priority'],
            'enabled' => true,
        ]);

        return back()->with('status', 'Keyword rule created.');
    }

    public function deleteRule(Merchant $merchant, AutomationRule $rule): RedirectResponse
    {
        $merchant->automationRules()->whereKey($rule->id)->firstOrFail()->delete();

        return back()->with('status', 'Keyword rule removed.');
    }
}
