<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variant_size_media', function (Blueprint $table) {
            $table->id();
            $table->string('size_key', 160)->unique();
            $table->string('size_label', 100);
            $table->string('image_url', 1000)->nullable();
            $table->boolean('image_is_reference')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_size_media');
    }
};
