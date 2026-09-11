<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('txn_code', 80)->nullable()->unique();
            $table->string('transaction_type', 40); // order_payment|purchase_payment|expense_payment|refund|transfer|opening_balance|adjustment
            $table->string('direction', 10); // in|out
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('SYP');
            $table->decimal('exchange_rate', 15, 4)->default(1);
            $table->decimal('amount_base', 15, 2);
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 80)->nullable();
            $table->string('counterparty_type', 40)->nullable(); // customer|supplier|internal
            $table->string('counterparty_id', 80)->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversed_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['transaction_type', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
