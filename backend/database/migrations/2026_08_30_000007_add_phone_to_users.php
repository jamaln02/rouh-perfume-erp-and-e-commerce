<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 20)->nullable()->after('email');
            });
        }

        // Preserve phone numbers already stored in profiles when they can be
        // migrated without creating duplicate user credentials.
        if (Schema::hasTable('profiles')) {
            $rows = DB::table('profiles')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->get(['id', 'phone']);

            foreach ($rows as $row) {
                $phone = trim((string) $row->phone);
                if (!preg_match('/^\+9639\d{8}$/', $phone)) continue;

                $alreadyUsed = DB::table('users')
                    ->where('phone', $phone)
                    ->where('id', '!=', $row->id)
                    ->exists();

                if (!$alreadyUsed) {
                    DB::table('users')->where('id', $row->id)->whereNull('phone')->update(['phone' => $phone]);
                }
            }
        }

        if (!$this->indexExists('users', 'users_phone_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('phone', 'users_phone_unique');
            });
        }
        if (!$this->indexExists('users', 'users_phone_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('phone', 'users_phone_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('users', 'users_phone_index')) DB::statement('DROP INDEX "users_phone_index"');
        if ($this->indexExists('users', 'users_phone_unique')) DB::statement('DROP INDEX "users_phone_unique"');
        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) { $table->dropColumn('phone'); });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return DB::table('sqlite_master')->where('type', 'index')->where('name', $index)->exists();
        }
        $indexes = Schema::getIndexes($table);
        foreach ($indexes as $definition) {
            if (($definition['name'] ?? '') === $index) return true;
        }
        return false;
    }
};
