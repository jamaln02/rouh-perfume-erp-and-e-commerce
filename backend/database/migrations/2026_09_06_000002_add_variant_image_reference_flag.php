<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_variants', 'image_is_reference')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->boolean('image_is_reference')->default(false)->after('image_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_variants', 'image_is_reference')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('image_is_reference');
            });
        }
    }
};
