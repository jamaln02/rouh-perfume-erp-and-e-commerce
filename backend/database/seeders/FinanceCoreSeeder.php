<?php

namespace Database\Seeders;

use App\Services\FinancePostingService;
use Illuminate\Database\Seeder;
use App\Models\AccountingPeriod;
use Carbon\Carbon;

class FinanceCoreSeeder extends Seeder
{
    public function run(): void
    {
        (new FinancePostingService())->ensureCoreAccounts();

        // Accounting periods are created by the controlled opening/go-live workflow.
        // Keeping this seeder free of an environment-sourced start date prevents
        // accounting configuration from leaking into store settings or fresh catalog seeds.

        $this->command?->info('Core financial accounts are ready.');
    }
}
