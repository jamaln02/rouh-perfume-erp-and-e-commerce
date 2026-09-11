<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrderLifecycleService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateOrderStatusController extends Controller
{
    public function __construct(private readonly OrderLifecycleService $lifecycle) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,accepted,confirmed,shipped,delivered,cancelled'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = trim((string) ($validated['reason'] ?? ''));
        if ($validated['status'] === 'cancelled' && $reason === '') {
            return response()->json(['ok' => false, 'message' => 'Cancellation reason is required.'], 422);
        }

        try {
            $order = $this->lifecycle->transition($id, $validated['status'], $reason ?: null);
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['ok' => false, 'message' => 'Order not found'], 404);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'Unable to update order status safely.'], 500);
        }

        $whatsappLink = null;
        try {
            if ($order->customer_phone) {
                $whatsappLink = (new WhatsAppNotificationService())->sendOrderStatusUpdate(
                    $order->customer_phone,
                    $id,
                    $order->status,
                    $order->customer_name,
                    'ar'
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to generate WhatsApp status update link', ['order_id' => $id]);
        }

        $request->attributes->set('audit.reason', $reason);
        return response()->json([
            'ok' => true,
            'status' => $order->status,
            'order' => $order,
            'whatsapp_link' => $whatsappLink,
        ]);
    }
}
