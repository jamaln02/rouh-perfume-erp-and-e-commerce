<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_variants', 'image_url')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('image_url', 1000)->nullable()->after('bottle_shape');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_variants', 'image_url')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('image_url');
            });
        }
    }
};
