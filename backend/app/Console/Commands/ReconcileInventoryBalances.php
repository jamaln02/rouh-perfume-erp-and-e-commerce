<?php

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use App\Models\Material;
use App\Services\InventoryCostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileInventoryBalances extends Command
{
    protected $signature = 'rouh:inventory-reconcile {--apply : Apply the reconstructed ledger balances to materials.current_stock and avg_unit_cost} {--dry-run : Show mismatches without changing data}';
    protected $description = 'Reconcile material stock/cost balances from the unified inventory movement ledger.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        if (!$apply && !$this->option('dry-run')) {
            $this->error('This command is read-only by default. Use --dry-run to inspect or --apply to repair balances.');
            return self::FAILURE;
        }

        $mismatches = 0;
        $changed = 0;

        DB::transaction(function () use (&$mismatches, &$changed, $apply): void {
            $materials = Material::query()->where('is_active', true)->orderBy('id')->get();
            foreach ($materials as $material) {
                $projection = app(InventoryCostingService::class)->project((int) $material->id);
                $stockDiff = abs($projection['stock'] - (float) $material->current_stock);
                $avgDiff = $projection['stock'] > 0.02
                    ? abs($projection['avg_cost'] - (float) $material->avg_unit_cost)
                    : 0.0;

                if ($stockDiff <= 0.02 && $avgDiff <= 0.02) {
                    continue;
                }

                $mismatches++;
                $this->line(sprintf(
                    '%s | stock %s -> %s | avg %s -> %s',
                    $material->name_ar ?: $material->name,
                    number_format((float) $material->current_stock, 6),
                    number_format($projection['stock'], 6),
                    number_format((float) $material->avg_unit_cost, 6),
                    number_format($projection['avg_cost'], 6),
                ));

                if ($apply) {
                    $locked = Material::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
                    $locked->current_stock = $projection['stock'];
                    $locked->avg_unit_cost = $projection['avg_cost'];
                    $locked->save();
                    $changed++;
                }
            }
        });

        if ($apply) {
            $this->info("Reconciliation complete. Mismatches found: {$mismatches}. Materials repaired: {$changed}.");
        } else {
            $this->info("Dry run complete. Mismatches found: {$mismatches}. No data was changed.");
        }

        return ($apply || $mismatches === 0) ? self::SUCCESS : self::FAILURE;
    }
}
