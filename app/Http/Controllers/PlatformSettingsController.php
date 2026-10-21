<?php

namespace App\Http\Controllers;

use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlatformSettingsController extends Controller
{
    public function index(): View
    {
        return view('platform.settings', [
            'registrationPaused' => PlatformSetting::enabled('registration_paused'),
            'outboundPaused' => PlatformSetting::enabled('outbound_paused'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_paused' => ['required', 'boolean'],
            'outbound_paused' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:250'],
            'confirmation' => ['required', 'string'],
        ]);
        if ($data['confirmation'] !== 'APPLY') {
            throw ValidationException::withMessages(['confirmation' => 'Type APPLY to confirm.']);
        }

        DB::transaction(function () use ($data, $request): void {
            foreach (['registration_paused', 'outbound_paused'] as $key) {
                $setting = PlatformSetting::where('key', $key)->lockForUpdate()->firstOrFail();
                $value = (bool) $data[$key];
                $before = $setting->enabled;
                if ($before === $value) {
                    continue;
                }
                $setting->update(['enabled' => $value, 'updated_by' => $request->user()->id]);
                PlatformAuditLog::create([
                    'actor_user_id' => $request->user()->id, 'action' => 'platform_setting_changed',
                    'target_type' => 'setting', 'target_id' => $setting->id,
                    'reason' => $data['reason'],
                    'changes' => ['key' => $key, 'from' => $before, 'to' => $value],
                ]);
            }
        });

        return back()->with('status', 'Platform safety controls updated.');
    }
}
