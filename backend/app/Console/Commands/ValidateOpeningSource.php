<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use App\Models\Material;

class ValidateOpeningSource extends Command
{
    protected $signature = 'rouh:validate-opening-source {file=database/seeders/data/rouh_opening_inventory.csv}';
    protected $description = 'Validate the approved ROUH opening physical-count file before production import.';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:[\\\/]/', $path)) $path = base_path($path);
        if (!is_file($path)) return $this->failValidation("Opening source not found: {$path}");

        $fh = fopen($path, 'rb');
        if (!$fh) return $this->failValidation('Unable to open opening source.');
        $header = array_map(static fn($v) => trim((string)$v, " \t\r\n\xEF\xBB\xBF"), fgetcsv($fh) ?: []);
        $map = array_flip($header);
        foreach (['name_ar','current_stock','avg_unit_cost','supplier_name','base_unit','material_category'] as $required) {
            if (!array_key_exists($required, $map)) { fclose($fh); return $this->failValidation("Missing column: {$required}"); }
        }

        $seen = [];
        $rows = 0;
        $inventoryValue = 0.0;
        $byCategory = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (!array_filter($r, fn($v) => trim((string)$v) !== '')) continue;
            $name = trim((string)$r[$map['name_ar']]);
            $qty = (float)$r[$map['current_stock']];
            $cost = (float)$r[$map['avg_unit_cost']];
            $unit = trim((string)$r[$map['base_unit']]);
            $category = trim((string)$r[$map['material_category']]);
            if ($name === '' || isset($seen[$name]) || $qty < 0 || $cost < 0 || $unit === '') {
                fclose($fh);
                return $this->failValidation("Invalid or duplicate row around material: {$name}");
            }
            $seen[$name] = true;
            $rows++;
            $inventoryValue += round($qty * $cost, 2);
            $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
        }
        fclose($fh);

        if ($rows !== 119) return $this->failValidation("Expected 119 inventory rows from the approved physical count; found {$rows}.");
        if (round($inventoryValue, 2) !== 190828.62) return $this->failValidation(sprintf('Expected opening inventory value 190,828.62 SYP; calculated %.2f.', $inventoryValue));

        $this->info('✓ Opening source structure valid.');
        $this->info("  Inventory rows: {$rows}");
        $this->info(sprintf('  Inventory value: %.2f SYP', $inventoryValue));
        foreach ($byCategory as $category => $count) $this->line("  {$category}: {$count}");

        if (Schema::hasTable('materials')) {
            $missing = [];
            foreach (array_keys($seen) as $name) {
                if (!Material::query()->where('name_ar', $name)->where('is_active', true)->exists()) $missing[] = $name;
            }
            if ($missing) return $this->failValidation('Materials missing from the active catalog: ' . implode('، ', $missing));
            $this->info('✓ All source materials exist in the active catalog.');
        } else {
            $this->warn('materials table is not present; catalog match was skipped (expected before migrations).');
        }

        $this->info('✓ Source is safe to use for the controlled opening-balance import.');
        return self::SUCCESS;
    }

private function failValidation(string $message): int
{
    $this->error('✗ ' . $message);
    return self::FAILURE;
}
}
