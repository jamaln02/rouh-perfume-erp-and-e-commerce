<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_role', 30)->nullable();
            $table->string('action', 30);
            $table->string('method', 10);
            $table->string('route', 255);
            $table->string('entity_type', 120)->nullable();
            $table->string('entity_id', 120)->nullable();
            $table->string('status', 20)->default('success');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'occurred_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('audit_logs'); }
};
