<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'public_tracking_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('public_tracking_token', 96)->nullable()->unique()->after('id');
            });
        }
        // Backfill legacy orders deterministically so historical order-success pages do not lose access.
        if (Schema::hasColumn('orders', 'public_tracking_token')) {
            foreach (Schema::getConnection()->table('orders')->whereNull('public_tracking_token')->pluck('id') as $id) {
                Schema::getConnection()->table('orders')->where('id', $id)->update(['public_tracking_token' => hash('sha384', (string)$id . '|legacy-tracking')]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'public_tracking_token')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique(['public_tracking_token']);
                $table->dropColumn('public_tracking_token');
            });
        }
    }
};
