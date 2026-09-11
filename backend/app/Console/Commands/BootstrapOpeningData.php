<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\FinancePostingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BootstrapOpeningData extends Command
{
    protected $signature = 'rouh:bootstrap-opening {--admin-email=} {--admin-password=}';

    protected $description = 'Prepare the accounting period, core accounts and admin for go-live. This command never creates opening balances, opening inventory, or fixed assets.';

    public function handle(FinancePostingService $finance): int
    {
        if (app()->environment('production')) {
            $this->error('This bootstrap command is disabled in production. Configure the server environment, run migrations, then use the controlled opening-balance workflow.');
            return self::FAILURE;
        }

        $adminEmail = trim((string) ($this->option('admin-email') ?: env('ROUH_OWNER_EMAIL', '')));
        $adminPassword = (string) ($this->option('admin-password') ?: env('ROUH_OWNER_PASSWORD', ''));
        if ($adminEmail === '' || $adminPassword === '') {
            $this->error('Provide --admin-email and --admin-password (or ROUH_OWNER_EMAIL / ROUH_OWNER_PASSWORD).');
            return self::FAILURE;
        }

        $configuredStart = trim((string) (DB::table('opening_balances')->orderBy('opening_balance_date')->value('opening_balance_date') ?? ''));
        if ($configuredStart === '') { $this->error('Create an opening balance first. The accounting start date is defined there.'); return self::FAILURE; }
        $start = Carbon::parse($configuredStart);
        $date = $start->toDateString();
        $year = (int) $start->format('Y');

        DB::transaction(function () use ($finance, $date, $year, $adminEmail, $adminPassword): void {
            $admin = User::where('email', $adminEmail)->first();
            if (!$admin) {
                $admin = User::create([
                    'name' => 'ROUH Owner',
                    'email' => $adminEmail,
                    'password' => Hash::make($adminPassword),
                ]);
            }
            auth()->loginUsingId($admin->id);

            AccountingPeriod::firstOrCreate(
                ['period_start' => $date],
                [
                    'name' => sprintf('FY %d', $year),
                    'period_end' => Carbon::parse($date)->endOfYear()->toDateString(),
                    'status' => 'open',
                    'created_by' => $admin->id,
                ]
            );

            $finance->ensureCoreAccounts();
        });

        $this->info('Development/bootstrap setup complete for accounting start ' . $date . '. No opening balance, inventory opening, or fixed asset opening data was created.');
        return self::SUCCESS;
    }
}
