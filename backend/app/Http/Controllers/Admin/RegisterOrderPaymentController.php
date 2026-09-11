<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Sale;
use App\Services\FinancePostingService;
use App\Models\FinancialPayment;
use App\Models\FinancialAccount;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegisterOrderPaymentController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 120) {
            return response()->json(['ok' => false, 'message' => 'A unique Idempotency-Key header is required for payment requests.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:cash,cash_on_delivery,sham_cash,bank_transfer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = DB::transaction(function () use ($id, $validated, $request, $idempotencyKey): array {
            $existing = FinancialPayment::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ((string) $existing->order_id !== (string) $id) {
                    throw new \RuntimeException('This Idempotency-Key is already assigned to another payment.');
                }
                return [
                    'order' => Order::whereKey($id)->firstOrFail(),
                    'sale_id' => $existing->sale_id,
                    'payment_reference' => 'order:' . $id,
                    'payment_amount' => (float) $existing->amount,
                    'payment_id' => $existing->id,
                    'payment_number' => $existing->payment_number,
                ];
            }

            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if (($order->status ?? $order->order_status) === 'cancelled') {
                throw new \RuntimeException('Cancelled orders cannot receive payments.');
            }
            $total = max(0.0, (float) $order->total);
            $currentPaid = max(0.0, (float) $order->paid_amount);
            $remaining = max(0.0, $total - $currentPaid);
            $amount = (float) $validated['amount'];

            if ($amount > $remaining + 0.0001) {
                abort(422, 'Payment amount cannot exceed the remaining balance.');
            }

            $newPaid = round($currentPaid + $amount, 2);
            $newRemaining = round(max(0.0, $total - $newPaid), 2);
            $status = $newRemaining <= 0.0001 ? 'paid' : 'partial';

            $order->payment_method = $validated['payment_method'];
            $order->paid_amount = $newPaid;
            $order->remaining_amount = $newRemaining;
            $order->payment_status = $status;
            $order->save();

            $sale = Sale::where('order_id', $order->id)
                ->where(function ($q) {
                    $q->whereNull('sale_status')->orWhere('sale_status', 'active');
                })
                ->latest('id')
                ->first();

            $accountCode = match ($validated['payment_method']) {
                'cash', 'cash_on_delivery' => '1000',
                'sham_cash' => '1020',
                'bank_transfer' => '1010',
            };
            $financialAccount = FinancialAccount::where('code', $accountCode)->firstOrFail();
            $payment = FinancialPayment::create([
                'payment_number' => 'REC-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'idempotency_key' => $idempotencyKey,
                'direction' => 'receipt',
                'amount' => $amount,
                'currency' => 'SYP',
                'exchange_rate' => 1,
                'amount_base' => $amount,
                'financial_account_id' => $financialAccount->id,
                'sale_id' => ($sale && ($sale->revenue_status ?? 'recognized') === 'recognized') ? $sale->id : null,
                'order_id' => (string) $order->id,
                'payment_method' => $validated['payment_method'],
                'reference' => $sale?->invoice_number ?: 'ORDER-' . $order->id,
                'notes' => $validated['notes'] ?? null,
                'payment_date' => now()->toDateString(),
                'status' => 'posted',
                'created_by' => $request->attributes->get('authUser')?->id,
            ]);

            if ($sale && ($sale->revenue_status ?? 'recognized') === 'recognized') {
                $sale->payment_method = $validated['payment_method'];
                $sale->paid_amount = $newPaid;
                $sale->remaining_amount = $newRemaining;
                $sale->payment_status = $status;
                if ($validated['notes'] ?? null) {
                    $sale->notes = trim(($sale->notes ? $sale->notes . ' | ' : '') . 'Payment: ' . $validated['notes']);
                }
                $sale->save();
                (new FinancePostingService())->postSalePayment($payment, $sale);
            } else {
                app(FinancePostingService::class)->postOrderDeposit($payment);
            }

            $paymentReference = $sale?->id ? 'sale:' . $sale->id : 'order:' . $order->id;
            $order->notes = trim(($order->notes ? $order->notes . ' | ' : '') . 'Payment ' . number_format($amount, 2, '.', '') . ' SYP via ' . $validated['payment_method'] . (($validated['notes'] ?? null) ? ' — ' . $validated['notes'] : ''));
            $order->save();

            return [
                'order' => $order->fresh(),
                'sale_id' => ($sale && ($sale->revenue_status ?? 'recognized') === 'recognized') ? $sale->id : null,
                'payment_reference' => $paymentReference,
                'payment_amount' => $amount,
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
            ];
            }, 3);
        } catch (\Illuminate\Database\QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'database is locked')) {
                return response()->json(['ok' => false, 'message' => 'Another payment is being processed for this order. Refresh and retry.'], 409);
            }

            throw $exception;
        }

        return response()->json(['ok' => true, ...$result]);
    }
}
