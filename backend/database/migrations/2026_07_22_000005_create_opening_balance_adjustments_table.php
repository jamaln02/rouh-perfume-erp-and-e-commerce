<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_id')->constrained('opening_balances')->onDelete('cascade');
            
            // Adjustment details
            $table->enum('adjustment_type', ['inventory', 'financial', 'payable', 'receivable']);
            $table->string('reference_id')->nullable(); // ID of the record being adjusted
            $table->string('reference_type')->nullable(); // Table name of the record being adjusted
            
            // Original values
            $table->text('original_values')->nullable(); // JSON
            $table->text('new_values')->nullable(); // JSON
            
            // Reason and audit
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignId('adjusted_by')->constrained('users')->onDelete('cascade');
            
            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            
            $table->timestamps();
            
            $table->index('opening_balance_id');
            $table->index('adjustment_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_adjustments');
    }
};