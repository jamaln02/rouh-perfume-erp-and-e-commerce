<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'order_status')) {
                $table->string('order_status', 30)->default('pending')->after('status');
            }
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 30)->default('unpaid')->after('order_status');
            }
            if (!Schema::hasColumn('orders', 'consumption_status')) {
                $table->string('consumption_status', 30)->default('not_consumed')->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('city');
            }
            if (!Schema::hasColumn('orders', 'shipping_fee')) {
                $table->decimal('shipping_fee', 15, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('orders', 'shipping_cost_internal')) {
                $table->decimal('shipping_cost_internal', 15, 2)->default(0)->after('shipping_fee');
            }
            if (!Schema::hasColumn('orders', 'payment_fees')) {
                $table->decimal('payment_fees', 15, 2)->default(0)->after('shipping_cost_internal');
            }
            if (!Schema::hasColumn('orders', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_fees');
            }
            if (!Schema::hasColumn('orders', 'remaining_amount')) {
                $table->decimal('remaining_amount', 15, 2)->default(0)->after('paid_amount');
            }
            if (!Schema::hasColumn('orders', 'refund_amount')) {
                $table->decimal('refund_amount', 15, 2)->default(0)->after('remaining_amount');
            }
            if (!Schema::hasColumn('orders', 'delivery_type')) {
                $table->string('delivery_type', 30)->default('delivery')->after('payment_method');
            }
            if (!Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status', 30)->default('pending')->after('delivery_type');
            }
            if (!Schema::hasColumn('orders', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('delivery_status');
            }
            if (!Schema::hasColumn('orders', 'prepared_at')) {
                $table->timestamp('prepared_at')->nullable()->after('accepted_at');
            }
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('prepared_at');
            }
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('orders', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('cancelled_at');
            }
            if (!Schema::hasColumn('orders', 'created_by_admin_id')) {
                $table->foreignId('created_by_admin_id')->nullable()->after('returned_at')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('orders', 'status') && Schema::hasColumn('orders', 'order_status')) {
            DB::table('orders')->whereNull('order_status')->update(['order_status' => DB::raw('status')]);
            DB::table('orders')->where('order_status', '')->update(['order_status' => DB::raw('status')]);
        }

        if (Schema::hasColumn('orders', 'total') && Schema::hasColumn('orders', 'subtotal')) {
            DB::table('orders')->where('subtotal', 0)->update(['subtotal' => DB::raw('total')]);
        }

        if (Schema::hasColumn('orders', 'total') && Schema::hasColumn('orders', 'remaining_amount')) {
            DB::table('orders')->where('remaining_amount', 0)->update(['remaining_amount' => DB::raw('total')]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'customer_id',
                'order_status',
                'payment_status',
                'consumption_status',
                'subtotal',
                'shipping_fee',
                'shipping_cost_internal',
                'payment_fees',
                'paid_amount',
                'remaining_amount',
                'refund_amount',
                'delivery_type',
                'delivery_status',
                'accepted_at',
                'prepared_at',
                'completed_at',
                'cancelled_at',
                'returned_at',
                'created_by_admin_id',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    if (in_array($column, ['customer_id', 'created_by_admin_id'], true)) {
                        try {
                            $table->dropForeign([$column]);
                        } catch (\Throwable $e) {
                            // ignore if foreign key does not exist
                        }
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
