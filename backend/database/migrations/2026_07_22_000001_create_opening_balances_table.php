<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->date('opening_balance_date')->unique();
            $table->enum('status', ['draft', 'confirmed', 'locked'])->default('draft');
            
            // Audit fields
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable();
            
            // Financial summary
            $table->decimal('total_inventory_value', 15, 2)->default(0);
            $table->decimal('total_cash_balance', 15, 2)->default(0);
            $table->decimal('total_bank_balance', 15, 2)->default(0);
            $table->decimal('total_payables', 15, 2)->default(0);
            $table->decimal('total_receivables', 15, 2)->default(0);
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('opening_balance_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};