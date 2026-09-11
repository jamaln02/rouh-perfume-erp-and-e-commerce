<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\OpeningBalance;
use App\Models\OpeningBalanceInventory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportOpeningInventory extends Command
{
    protected $signature = 'rouh:import-opening-inventory {file=database/seeders/data/rouh_opening_inventory.csv : Approved CSV path relative to backend or absolute} {--date= : Opening date} {--commit : Persist the approved opening draft. Without this flag the command is validation-only} {--created-by= : User ID to attribute the opening draft to (required with --commit)}';
    protected $description = 'Validate and optionally create a draft opening inventory from the approved physical-count CSV without directly mutating stock.';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (!Str::startsWith($path, ['/', '\\']) && !preg_match('/^[A-Za-z]:[\\\/]/', $path)) {
            $path = base_path($path);
        }
        if (!is_file($path)) {
            $this->error("CSV not found: {$path}");
            return self::FAILURE;
        }

        $openingDate = trim((string) ($this->option('date') ?: ''));
        if ($openingDate === '') {
            $openingDate = trim((string) (OpeningBalance::query()->orderBy('opening_balance_date')->value('opening_balance_date') ?? ''));
        }
        if ($openingDate === '') {
            $this->error('Opening date is required. Use --date=YYYY-MM-DD or create the opening balance first.');
            return self::FAILURE;
        }
        try {
            \Carbon\Carbon::createFromFormat('Y-m-d', $openingDate);
        } catch (\Throwable) {
            $this->error('Opening date must use YYYY-MM-DD.');
            return self::FAILURE;
        }

        if (app()->environment('production') && !$this->option('commit')) {
            $this->info('Production mode: validation only. Add --commit to create the draft, after enabling ROUH_ALLOW_OPENING_IMPORT=true.');
        }
        $createdBy = null;
        if ($this->option('commit')) {
            $createdBy = (int) ($this->option('created-by') ?? 0);
            if ($createdBy <= 0 || !User::query()->whereKey($createdBy)->where('is_active', true)->exists()) {
                $this->error('With --commit you must provide --created-by=<active user id>.');
                return self::FAILURE;
            }
        }

        if ($this->option('commit') && ! (bool) config('rouh.opening_inventory.allow_commit_in_production', false) && app()->environment('production')) {
            $this->error('Opening import is disabled in production. Set ROUH_ALLOW_OPENING_IMPORT=true only for the controlled go-live import window.');
            return self::FAILURE;
        }

        $fh = fopen($path, 'rb');
        if (!$fh) {
            $this->error('Could not open CSV.');
            return self::FAILURE;
        }

        $header = fgetcsv($fh);
        if (!is_array($header)) { fclose($fh); $this->error('CSV is empty.'); return self::FAILURE; }
        $header = array_map(static fn ($v) => trim((string) $v, " \t\r\n\xEF\xBB\xBF"), $header);
        $map = array_flip($header);
        $currentFormat = isset($map['name_ar'], $map['current_stock'], $map['avg_unit_cost'], $map['supplier_name']);
        $legacyFormat = isset($map['Material ID'], $map['المادة'], $map['الكمية'], $map['الوحدة'], $map['تكلفة الوحدة SYP'], $map['قيمة الافتتاح SYP'], $map['المورد']);
        if (!$currentFormat && !$legacyFormat) { fclose($fh); $this->error('Unsupported opening inventory CSV format.'); return self::FAILURE; }
        $rows=[]; $line=1; $seen=[];
        while (($row=fgetcsv($fh))!==false) {
            $line++;
            if (!array_filter($row, fn($v)=>trim((string)$v)!=='')) continue;
            if ($currentFormat) {
                $nameAr=trim((string)$row[$map['name_ar']]); $qty=(float)$row[$map['current_stock']]; $unitCost=(float)$row[$map['avg_unit_cost']]; $supplier=trim((string)$row[$map['supplier_name']])?:null;
                $baseUnit=isset($map['base_unit']) ? trim((string)$row[$map['base_unit']]) : null;
                $category=isset($map['material_category']) ? trim((string)$row[$map['material_category']]) : null;
                if ($nameAr==='') throw new \RuntimeException("Missing name_ar at CSV line {$line}.");
                if ($qty < 0 || $unitCost < 0) throw new \RuntimeException("Invalid quantity/cost at CSV line {$line}: {$nameAr}");
                if ($baseUnit === '') throw new \RuntimeException("Missing base_unit at CSV line {$line}: {$nameAr}");
                if (isset($seen[$nameAr])) throw new \RuntimeException("Duplicate material {$nameAr} at CSV line {$line}.");
                $seen[$nameAr] = true;
                $rows[]=['nameAr'=>$nameAr,'qty'=>$qty,'unitCost'=>$unitCost,'supplier'=>$supplier,'baseUnit'=>$baseUnit,'category'=>$category];
            } else {
                $materialId=(int)$row[$map['Material ID']]; $nameAr=trim((string)$row[$map['المادة']]); $qty=(float)$row[$map['الكمية']]; $unit=trim((string)$row[$map['الوحدة']]); $unitCost=(float)$row[$map['تكلفة الوحدة SYP']]; $supplier=trim((string)$row[$map['المورد']])?:null;
                if ($materialId<=0 || $nameAr==='' || $unit==='' || $qty<0 || $unitCost<0) throw new \RuntimeException("Invalid legacy row at CSV line {$line}.");
                if (isset($seen[$materialId])) throw new \RuntimeException("Duplicate material ID {$materialId} at CSV line {$line}.");
                $seen[$materialId] = true;
                $rows[]=['materialId'=>$materialId,'nameAr'=>$nameAr,'qty'=>$qty,'unit'=>$unit,'unitCost'=>$unitCost,'supplier'=>$supplier,'baseUnit'=>$unit,'category'=>null];
            }
        }
        fclose($fh);
        if (!$rows) { $this->error('CSV contains no inventory rows.'); return self::FAILURE; }

        if (!$this->option('commit')) { $this->info(sprintf('Validated %d inventory rows. Dry-run only; no database records changed.', count($rows))); return self::SUCCESS; }

        try {
            DB::transaction(function () use ($rows, $openingDate, $createdBy, $currentFormat): void {
                $opening = OpeningBalance::query()->whereDate('opening_balance_date', $openingDate)->lockForUpdate()->first();
                if ($opening && !$opening->canBeEdited()) throw new \RuntimeException('The opening balance is no longer editable.');
                if (!$opening) {
                    if (!$createdBy) throw new \RuntimeException('Opening balance does not exist. With --commit provide --created-by so a draft can be created.');
                    $opening=OpeningBalance::create(['opening_balance_date'=>$openingDate,'status'=>'draft','created_by'=>$createdBy,'notes'=>'Approved physical opening inventory import.']);
                }
                foreach ($rows as $row) {
                    $material=$currentFormat
                        ? Material::query()->where('is_active',true)->where('name_ar',$row['nameAr'])->first()
                        : Material::query()->whereKey($row['materialId'])->where('is_active',true)->first();
                    if (!$material) throw new \RuntimeException('Material not found: '.$row['nameAr']);
                    $unit=$material->base_unit; $qty=$row['qty']; $cost=$row['unitCost'];
                    if (($row['baseUnit'] ?? $unit) !== $unit) throw new \RuntimeException("Unit mismatch for {$row['nameAr']}: CSV={$row['baseUnit']}, material={$unit}.");
                    $allowedCategories = ['perfume_oil','alcohol','packaging','bottle','cap','sprayer','box','shopping_bag','other_packaging','other_raw_material'];
                    if ($row['category'] !== null && $row['category'] !== '' && !in_array($row['category'], $allowedCategories, true)) {
                        throw new \RuntimeException("Unsupported material_category for {$row['nameAr']}: {$row['category']}");
                    }
                    $openingRow=OpeningBalanceInventory::query()->updateOrCreate(
                        ['opening_balance_id'=>$opening->id,'material_id'=>$material->id],
                        ['material_name'=>$material->name,'material_name_ar'=>$material->name_ar,'material_type'=>$this->openingMaterialType($material->material_category, $material->subcategory),'unit'=>$unit,'quantity'=>$qty,'unit_cost'=>$cost,'currency'=>'SYP','exchange_rate'=>1,'supplier'=>$row['supplier'],'notes'=>'Imported from approved physical opening count.']
                    );
                    $openingRow->save();
                }
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage()); return self::FAILURE;
        }

        $this->info(sprintf('Validated %d opening inventory rows.', count($rows)));
        if ($this->option('commit')) {
            $this->info('Opening inventory draft created/verified. No material.current_stock or avg_unit_cost was mutated by this command. Confirm the opening balance to post the official stock movements and GL entry.');
        } else {
            $this->info('Dry-run only: no database records were changed.');
        }

        return self::SUCCESS;
    }

    private function openingMaterialType(string $category, ?string $subcategory = null): string
    {
        if ($category === 'packaging') {
            return match ($subcategory) {
                'bottle' => 'bottle',
                'box' => 'box',
                'bag' => 'shopping_bag',
                default => 'other_packaging',
            };
        }
        return in_array($category, ['perfume_oil','alcohol','bottle','cap','sprayer','box','shopping_bag','other_packaging','other_raw_material'], true)
            ? $category
            : 'other_raw_material';
    }
}
