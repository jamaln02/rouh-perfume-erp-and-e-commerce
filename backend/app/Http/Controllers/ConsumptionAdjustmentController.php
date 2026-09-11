<?php
namespace App\Http\Controllers;

use App\Models\ConsumptionAdjustmentRequest;
use App\Models\InventoryMovement;
use App\Models\OrderConsumption;
use App\Models\OrderConsumptionItem;
use App\Models\Sale;
use App\Models\OrderCostSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\UnitConversionService;
use App\Services\FinancePostingService;

class ConsumptionAdjustmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = ConsumptionAdjustmentRequest::with(['item.material','requester:id,name,email','reviewer:id,name,email'])
            ->orderByDesc('created_at');
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('order_id')) $q->where('order_id', $request->string('order_id'));
        return response()->json($q->paginate(min(max((int)$request->input('per_page',50),1),100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_consumption_item_id' => ['required','integer','exists:order_consumption_items,id'],
            'requested_actual_qty' => ['required','numeric','min:0'],
            'reason' => ['required','string','max:2000'],
        ]);
        $item = OrderConsumptionItem::with(['consumption','material'])->findOrFail($validated['order_consumption_item_id']);
        if (!$item->consumption || !$item->consumption->order_id) return response()->json(['ok'=>false,'message'=>'Consumption record is invalid.'],422);
        $existing = ConsumptionAdjustmentRequest::where('order_consumption_item_id',$item->id)->where('status','pending')->first();
        if ($existing) return response()->json(['ok'=>false,'message'=>'A pending correction request already exists for this item.'],422);
        if ((float)$validated['requested_actual_qty'] < 0) return response()->json(['ok'=>false,'message'=>'Quantity cannot be negative.'],422);
        $req = ConsumptionAdjustmentRequest::create([
            'order_id'=>$item->consumption->order_id,
            'order_consumption_id'=>$item->order_consumption_id,
            'order_consumption_item_id'=>$item->id,
            'material_id'=>$item->material_id,
            'old_actual_qty'=>$item->actual_qty,
            'requested_actual_qty'=>$validated['requested_actual_qty'],
            'reason'=>$validated['reason'],
            'status'=>'pending',
            'requested_by'=>(int)$request->attributes->get('authUser')->id,
        ]);
        return response()->json(['ok'=>true,'request'=>$req->load(['item.material','requester:id,name,email'])],201);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required','in:approved,rejected'],
            'review_note' => ['nullable','string','max:2000'],
        ]);
        $result = DB::transaction(function () use ($request,$id,$validated) {
            $adj = ConsumptionAdjustmentRequest::with(['item','item.consumption','material'])->lockForUpdate()->findOrFail($id);
            if ($adj->status !== 'pending') throw new \RuntimeException('This correction request has already been reviewed.');
            $reviewerId = (int)$request->attributes->get('authUser')->id;
            if ($validated['decision'] === 'rejected') {
                $adj->status='rejected'; $adj->reviewed_by=$reviewerId; $adj->reviewed_at=now(); $adj->review_note=$validated['review_note'] ?? null; $adj->save();
                return $adj;
            }
            $item = OrderConsumptionItem::lockForUpdate()->findOrFail($adj->order_consumption_item_id);
            $material = $item->material()->lockForUpdate()->first();
            if (!$material) throw new \RuntimeException('Material not found.');
            $old = (float)$item->actual_qty;
            $new = (float)$adj->requested_actual_qty;
            $delta = $new - $old;
            $conversion = (new UnitConversionService())->convert(abs($delta), (string)($item->unit ?: $material->base_unit), (string)$material->base_unit);
            if ($conversion === null) throw new \RuntimeException('Unit mismatch while applying the consumption correction.');

            // Preserve the original issue cost captured on the immutable inventory movement.
            // Never revalue historical COGS using today's moving-average cost.
            $originalMovement = InventoryMovement::query()
                ->where('reference_type', 'order_consumption')
                ->where('reference_id', (string) $item->order_consumption_id)
                ->where('movement_type', 'consumption')
                ->where('material_id', $material->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();
            if (!$originalMovement) throw new \RuntimeException('Original consumption movement was not found; the correction cannot be applied safely.');
            $originalUnitCost = (float) $originalMovement->unit_cost;
            $deltaCost = round($conversion * $originalUnitCost * ($delta >= 0 ? 1 : -1), 4);
            $previousStock = (float) $material->current_stock;
            $previousAvg = (float) $material->avg_unit_cost;
            $previousValue = $previousStock * $previousAvg;
            $newStock = $previousStock - ($delta >= 0 ? $conversion : -$conversion);
            if ($newStock < -0.0000001) throw new \RuntimeException("Insufficient stock to apply the correction for {$material->name}.");
            $movementValue = round($conversion * $originalUnitCost, 4);
            $newValue = $delta >= 0 ? ($previousValue - $movementValue) : ($previousValue + $movementValue);
            if ($newValue < -0.01) throw new \RuntimeException("The correction would make the inventory value negative for {$material->name}.");
            $newStock = max(0.0, $newStock);
            $newAvg = $newStock > 0 ? round(max(0.0, $newValue) / $newStock, 6) : 0.0;
            $material->current_stock = $newStock;
            $material->avg_unit_cost = $newAvg;
            $material->save();
            if (abs($delta) > 0.0000001) {
                InventoryMovement::create([
                    'material_id'=>$material->id,
                    'movement_type'=>$delta>0?'consumption_adjustment':'consumption_adjustment_reversal',
                    'quantity'=>abs($delta),
                    'unit'=>$item->unit ?: $material->base_unit,
                    'quantity_base'=>$conversion,
                    'previous_stock'=>$previousStock,
                    'new_stock'=>(float)$material->current_stock,
                    'unit_cost'=>$originalUnitCost,
                    'total_cost'=>round($conversion * $originalUnitCost, 4),
                    'reference_type'=>'consumption_adjustment_request',
                    'reference_id'=>(string)$adj->id,
                    'notes'=>'Approved correction of actual material consumption',
                    'movement_date'=>now(),
                    'created_by'=>$reviewerId,
                ]);
            }
            $variance = $new - (float)$item->expected_qty;
            $item->actual_qty=$new; $item->variance_qty=$variance; $item->variance_classification=abs($variance)<0.00005?'normal':($variance>0?'overage':'shortage'); $item->save();
            $consumption = OrderConsumption::lockForUpdate()->findOrFail($item->order_consumption_id);

            // Adjust the existing cost snapshot and sale by the exact approved delta.
            // This keeps the original historical cost basis intact while recording the correction.
            $consumption->total_material_cost = round((float)$consumption->total_material_cost + $deltaCost, 4);
            $consumption->total_production_cost = round(
                (float)$consumption->total_material_cost
                + (float)($consumption->labor_cost ?? 0)
                + (float)($consumption->electricity_cost ?? 0)
                + (float)($consumption->overhead_cost ?? 0),
                4
            );
            $consumption->save();

            $snapshot = OrderCostSnapshot::where('order_consumption_id', $consumption->id)->lockForUpdate()->first();
            if ($snapshot) {
                $snapshot->material_cogs = round((float)$snapshot->material_cogs + $deltaCost, 2);
                $snapshot->total_cogs = round((float)$snapshot->total_cogs + $deltaCost, 2);
                $snapshot->gross_profit = round((float)$snapshot->gross_profit - $deltaCost, 2);
                $snapshot->net_profit = round((float)$snapshot->net_profit - $deltaCost, 2);
                $snapshot->notes = trim(($snapshot->notes ?? '') . "\nApproved consumption cost correction #{$adj->id}: " . $deltaCost . ' SYP.');
                $snapshot->save();
            }

            app(FinancePostingService::class)->postMaterialConsumptionAdjustment((string)$adj->id, $deltaCost, (string)$consumption->id, (string)$consumption->order_id);
            $sale = Sale::where('order_id',$consumption->order_id)->where('sale_status','active')->lockForUpdate()->first();
            if ($sale) {
                $sale->cost = round((float)$sale->cost + $deltaCost, 2);
                $sale->cost_syp = round((float)$sale->cost_syp + $deltaCost, 2);
                $sale->profit = round((float)$sale->total_price_syp - (float)$sale->cost_syp, 2);
                $sale->profit_syp = $sale->profit;
                $sale->save();
            }
            $adj->status='approved'; $adj->reviewed_by=$reviewerId; $adj->reviewed_at=now(); $adj->review_note=$validated['review_note'] ?? null; $adj->save();
            return $adj->load(['item.material','requester:id,name,email','reviewer:id,name,email']);
        });
        return response()->json(['ok'=>true,'request'=>$result]);
    }
}
