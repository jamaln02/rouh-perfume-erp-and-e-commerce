<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64);
            $table->string('permission', 100);
            $table->boolean('allowed')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
            $table->index(['user_id', 'allowed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
