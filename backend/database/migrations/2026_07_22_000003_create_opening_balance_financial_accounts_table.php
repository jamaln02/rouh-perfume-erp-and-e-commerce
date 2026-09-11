<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_id')->constrained('opening_balances')->onDelete('cascade');
            
            // Account identification
            $table->string('account_name');
            $table->string('account_name_ar')->nullable();
            $table->enum('account_type', ['cash', 'bank', 'payment_gateway', 'other']);
            $table->string('currency')->default('SYP');
            
            // Balance
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('exchange_rate', 10, 2)->default(13500);
            $table->decimal('balance_syp', 15, 2)->default(0);
            
            // Additional info
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('opening_balance_id');
            $table->index('account_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_financial_accounts');
    }
};