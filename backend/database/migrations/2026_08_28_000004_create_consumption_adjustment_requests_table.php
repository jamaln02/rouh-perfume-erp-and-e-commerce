<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('consumption_adjustment_requests', function(Blueprint $table){
            $table->id();
            $table->string('order_id', 120)->index();
            $table->unsignedBigInteger('order_consumption_id')->index();
            $table->unsignedBigInteger('order_consumption_item_id')->index();
            $table->unsignedBigInteger('material_id')->index();
            $table->decimal('old_actual_qty',15,4)->default(0);
            $table->decimal('requested_actual_qty',15,4);
            $table->text('reason');
            $table->string('status',20)->default('pending')->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->foreign('order_consumption_id')->references('id')->on('order_consumptions')->cascadeOnDelete();
            $table->foreign('order_consumption_item_id', 'car_item_fk')->references('id')->on('order_consumption_items')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('materials')->restrictOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('consumption_adjustment_requests'); }
};
