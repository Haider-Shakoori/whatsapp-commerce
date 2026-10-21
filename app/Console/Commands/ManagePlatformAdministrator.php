<?php

namespace App\Console\Commands;

use App\Models\PlatformAdministrator;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManagePlatformAdministrator extends Command
{
    protected $signature = 'commerce:platform:admin
        {email : An EXISTING user email}
        {action : grant or revoke}
        {--confirm : Explicit operator confirmation}';

    protected $description = 'Server-console-only platform administrator provisioning; never use public registration';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $action = $this->argument('action');
        if (! $user || ! in_array($action, ['grant', 'revoke'], true)) {
            $this->error('Use an existing exact email and grant/revoke.');

            return self::FAILURE;
        }
        if (! $this->option('confirm')) {
            $this->warn('Dry run. Run with --confirm from an authorized server terminal to apply.');
            $this->line($action.' platform access for '.$user->email);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $action): void {
            if ($action === 'grant') {
                PlatformAdministrator::firstOrCreate(['user_id' => $user->id]);
            } else {
                PlatformAdministrator::where('user_id', $user->id)->delete();
            }
            PlatformAuditLog::create([
                'actor_user_id' => null,
                'action' => $action === 'grant' ? 'platform_admin_granted' : 'platform_admin_revoked',
                'target_type' => 'user', 'target_id' => $user->id,
                'reason' => 'Authorized server-console operator',
                'changes' => ['source' => 'artisan'],
            ]);
        });

        $this->info('Platform administrator record updated. Review audit log.');

        return self::SUCCESS;
    }
}
