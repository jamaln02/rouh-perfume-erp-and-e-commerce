<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_discounts', function (Blueprint $table) {
            if (!Schema::hasColumn('quiz_discounts', 'user_id')) {
                $table->string('user_id', 64)->nullable()->after('phone');
                $table->index('user_id');
            }
            if (!Schema::hasColumn('quiz_discounts', 'customer_name')) {
                $table->string('customer_name', 200)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('quiz_discounts', 'discount_percent')) {
                $table->unsignedTinyInteger('discount_percent')->default(10)->after('discount_code');
            }
        });

        // Upgrade previously issued quiz codes without exposing or regenerating them.
        // Matching the unique stored phone lets legacy unused codes keep working for
        // the same registered customer under the new security rules.
        $defaultPercent = 10;
        $settingsPercent = DB::table('store_settings')->where('key', 'quiz_discount_percent')->value('value');
        if (is_numeric($settingsPercent)) {
            $defaultPercent = max(1, min(100, (int) $settingsPercent));
        }

        DB::table('quiz_discounts as q')
            ->join('users as u', 'u.phone', '=', 'q.phone')
            ->whereNull('q.user_id')
            ->update([
                'q.user_id' => DB::raw('u.id'),
                'q.customer_name' => DB::raw('u.name'),
                'q.discount_percent' => $defaultPercent,
                'q.updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('quiz_discounts', function (Blueprint $table) {
            $columns = [];
            foreach (['discount_percent', 'customer_name', 'user_id'] as $column) {
                if (Schema::hasColumn('quiz_discounts', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
