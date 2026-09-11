<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('requested_qty', 16, 4);
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('requested_by', 64);
            $table->timestamps();
            $table->index(['status','created_at']);
            $table->index(['requested_by','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_requests'); }
};
