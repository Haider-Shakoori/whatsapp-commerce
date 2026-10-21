<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['key', 'enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function enabled(string $key): bool
    {
        if (! in_array($key, ['registration_paused', 'outbound_paused'], true)) {
            return false;
        }

        // A missing control has the safe default: no platform pause.
        // The global messaging transport remains independently disabled.
        return (bool) static::where('key', $key)->value('enabled');
    }
}
