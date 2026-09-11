<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'customer_address')) {
                $table->text('customer_address')->nullable()->change();
            }
            if (Schema::hasColumn('orders', 'city')) {
                $table->string('city', 100)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'customer_address')) {
                $table->text('customer_address')->nullable(false)->change();
            }
            if (Schema::hasColumn('orders', 'city')) {
                $table->string('city', 100)->nullable(false)->change();
            }
        });
    }
};
