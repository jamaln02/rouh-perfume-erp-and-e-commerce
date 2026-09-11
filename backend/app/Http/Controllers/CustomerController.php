<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    private function enrichOrdersWithDetails($orders, bool $hideFinancials = false)
    {
        foreach ($orders as $order) {
            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            if ($hideFinancials) {
                $items = $items->map(function ($item) {
                    unset($item->price, $item->line_discount_amount, $item->line_total);
                    return $item;
                });
                foreach (['subtotal','total','shipping_cost','shipping_fee','discount_amount','paid_amount','remaining_amount'] as $field) {
                    unset($order->{$field});
                }
                unset($order->payment_method, $order->payment_status);
            }
            $order->items = $items;
            $order->customization = [
                'oil_grams' => $order->oil_grams ?? null,
                'bottle_size_ml' => $order->bottle_size_ml ?? null,
            ];
        }
        return $orders;
    }

    private function enrichCustomer(Customer $customer, Request $request): Customer
    {
        $role = $request->attributes->get('authRole') ?: 'admin';
        $hideFinancials = $role === 'employee';

        $orders = DB::table('orders')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get();
        $orders = $this->enrichOrdersWithDetails($orders, $hideFinancials);

        $customer->setAttribute('loyalty_points', (int) ($customer->loyalty_points ?? 0));
        $customer->setAttribute('orders', $orders);
        $customer->setAttribute('order_count', $orders->count());
        if (!$hideFinancials) {
            $customer->setAttribute('total_spent_calculated', round((float) DB::table('sales')
                ->where('customer_id', $customer->id)
                ->where(function ($q) {
                    $q->whereNull('sale_status')->orWhere('sale_status', 'active');
                })->sum('total_price_syp'), 2));
        } else {
            $customer->setAttribute('total_spent_calculated', null);
        }

        if ($hideFinancials) {
            $customer->setAttribute('total_spent', null);
            $customer->setAttribute('sales', []);
            $customer->makeHidden(['preferred_payment_method']);
        }

        return $customer;
    }

    public function index(Request $request)
    {
        $customers = Customer::query()->orderByDesc('last_purchase_date')->get();
        $customers->each(fn(Customer $customer) => $this->enrichCustomer($customer, $request));
        return response()->json($customers);
    }

    public function show(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        return response()->json($this->enrichCustomer($customer, $request));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|min:6|max:32|unique:customers,phone',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:120',
            'preferred_payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
            'status' => 'sometimes|in:active,inactive,blocked',
        ]);
        $customer = Customer::create($validated);
        return response()->json($customer, 201);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $before = $customer->only(['name','phone','email','address','city','notes','status']);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'phone' => 'sometimes|string|min:6|max:32|unique:customers,phone,' . $id,
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000',
            'city' => 'nullable|string|max:120',
            'preferred_payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:5000',
            'status' => 'sometimes|in:active,inactive,blocked',
            'reason' => 'nullable|string|max:1000',
        ]);
        $reason = $validated['reason'] ?? null;
        unset($validated['reason']);
        $customer->update($validated);
        $request->attributes->set('audit.before', $before);
        $request->attributes->set('audit.after', $customer->only(array_keys($before)));
        $request->attributes->set('audit.reason', $reason);
        return response()->json($customer);
    }


    public function updateNotes(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $before = ['notes' => $customer->notes];
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $customer->notes = $validated['notes'] ?? null;
        $customer->save();
        $request->attributes->set('audit.before', $before);
        $request->attributes->set('audit.after', ['notes' => $customer->notes]);
        $request->attributes->set('audit.reason', $validated['reason'] ?? 'Customer note updated');
        return response()->json($customer);
    }

    public function destroy(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $before = $customer->only(['status']);
        $reason = $request->input('reason');
        $customer->update(['status' => 'inactive']);
        $request->attributes->set('audit.before', $before);
        $request->attributes->set('audit.after', ['status' => 'inactive']);
        $request->attributes->set('audit.reason', $reason ?: 'Customer archived from administration');
        return response()->json(['message' => 'Customer archived']);
    }

    public function searchByPhone(Request $request, $phone)
    {
        $customer = Customer::where('phone', $phone)->orWhere('email', $phone)->first();
        if (!$customer) return response()->json(['message' => 'Customer not found'], 404);
        return response()->json($this->enrichCustomer($customer, $request));
    }
}
