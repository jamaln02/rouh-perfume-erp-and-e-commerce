<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn("fixed_assets", "useful_life_months")) {
            Schema::table("fixed_assets", function (Blueprint $table) {
                $table->unsignedInteger("useful_life_months")->nullable()->after("useful_life_years");
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn("fixed_assets", "useful_life_months")) {
            Schema::table("fixed_assets", function (Blueprint $table) {
                $table->dropColumn("useful_life_months");
            });
        }
    }
};
