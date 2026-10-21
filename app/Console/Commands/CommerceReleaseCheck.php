<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommerceReleaseCheck extends Command
{
    protected $signature = 'commerce:release:check {--production : Enforce production security and deployment preconditions}';

    protected $description = 'Run safe read-only environment and database deployment preflight (no secret values printed)';

    public function handle(): int
    {
        $issues = [];
        $checks = [];
        $check = static function (bool $passed, string $message) use (&$checks, &$issues): void {
            $checks[] = [$passed, $message];
            if (! $passed) {
                $issues[] = $message;
            }
        };

        foreach ([
            'merchants', 'merchant_subscriptions', 'subscription_plans',
            'subscription_usages', 'platform_administrators', 'platform_settings',
            'outbound_messages', 'inbound_events', 'payment_attempts',
            'orders', 'customers', 'conversations', 'jobs', 'failed_jobs',
        ] as $table) {
            $check(Schema::hasTable($table), 'Required database table exists: '.$table);
        }

        $driver = DB::connection()->getDriverName();
        if ($this->option('production')) {
            $check(config('app.env') === 'production', 'APP_ENV is production');
            $check(config('app.debug') === false, 'APP_DEBUG is disabled');
            $check(is_string(config('app.key')) && strlen(config('app.key')) >= 32, 'APP_KEY is set');
            $check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS');
            $check($driver === 'mysql', 'Production is configured to run on the validated MySQL driver');
            $check((bool) config('session.encrypt'), 'Session encryption is enabled');
            $check(! in_array(config('session.driver'), ['array', 'cookie'], true), 'Server-side session storage is enabled');
            $check(! in_array(config('queue.default'), ['sync', 'null'], true), 'Asynchronous queue backend is enabled');

            $transport = config('commerce.messaging_driver');
            $check(in_array($transport, ['disabled', 'relaywa'], true), 'Messaging driver is recognized');
            if ($transport === 'relaywa') {
                $url = parse_url((string) config('commerce.relaywa_base_url'));
                $host = is_array($url) ? ($url['host'] ?? null) : null;
                $check(is_array($url) && ($url['scheme'] ?? null) === 'https'
                    && is_string($host)
                    && strcasecmp((string) config('commerce.relaywa_allowed_host'), $host) === 0
                    && $host !== '', 'RelayWA HTTPS origin matches an explicit host allowlist');
            }
        }

        if (config('queue.default') === 'database') {
            $retry = (int) config('queue.connections.database.retry_after', 0);
            $check($retry >= 150, 'Database queue retry_after is at least 150 seconds (send worker timeout 90s)');
        }

        foreach ($checks as [$passed, $message]) {
            $this->line(($passed ? '[PASS] ' : '[FAIL] ').$message);
        }
        if ($issues !== []) {
            $this->error(count($issues).' release preflight checks failed. Do not deploy.');

            return self::FAILURE;
        }
        $this->info('Preflight checks passed. This does NOT verify backups, infrastructure, DNS, live provider delivery or payment readiness.');

        return self::SUCCESS;
    }
}
