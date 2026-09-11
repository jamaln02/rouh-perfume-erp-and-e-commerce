<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the `favorites` table for DB-backed user favorites (wishlist).
 *
 * Schema notes:
 *   - `user_id` references `users.id` which is an auto-increment BIGINT, so we
 *     use foreignId() (bigInteger + FK constraint).
 *   - `product_id` references `products.id` which is a UUID (char(36)), so we
 *     declare it as uuid() and add the FK manually.
 *   - UNIQUE(user_id, product_id) prevents duplicate favorites per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->timestamps();

            // A user can favorite a given product at most once.
            $table->unique(['user_id', 'product_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
