<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('full_name', 200)->nullable();
            $table->string('phone', 32)->nullable();
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
