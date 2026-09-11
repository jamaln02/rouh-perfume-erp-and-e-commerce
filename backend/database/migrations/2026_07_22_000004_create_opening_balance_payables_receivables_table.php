<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_payables_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_id')->constrained('opening_balances')->onDelete('cascade');
            
            // Party identification
            $table->string('party_name');
            $table->string('party_name_ar')->nullable();
            $table->enum('type', ['payable', 'receivable']);
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            // Amount
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency')->default('SYP');
            $table->decimal('exchange_rate', 10, 2)->default(13500);
            $table->decimal('amount_syp', 15, 2)->default(0);
            
            // Additional info
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('opening_balance_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_payables_receivables');
    }
};