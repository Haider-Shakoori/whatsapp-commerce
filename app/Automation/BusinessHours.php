<?php

namespace App\Automation;

use App\Models\AutomationSetting;
use App\Models\Merchant;

final class BusinessHours
{
    public function isOpen(Merchant $merchant, AutomationSetting $settings): bool
    {
        $weekly = $settings->weekly_hours ?? [];
        if (! is_array($weekly) || $weekly === []) {
            return true; // Unconfigured hours must never trigger an after-hours reply.
        }

        $now = now($merchant->timezone ?: 'UTC');
        $range = $weekly[(string) $now->dayOfWeekIso] ?? null;
        if (! is_array($range) || ! isset($range['start'], $range['end'])) {
            return false;
        }

        $clock = $now->format('H:i');

        return $clock >= $range['start'] && $clock < $range['end'];
    }
}
