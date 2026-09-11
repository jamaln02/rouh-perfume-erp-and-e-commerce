<?php

namespace App\Services;

use App\Models\Customer;

class CustomerReferenceService
{
    public function findOrCreate(array $validated): Customer
    {
        return $this->findOrCreateForAdmin($validated);
    }

    public function findOrCreateForPublicOrder(array $validated): Customer
    {
        $phone = trim((string) ($validated['customer_phone'] ?? ''));
        $email = trim((string) ($validated['customer_email'] ?? ''));

        if ($phone !== '') {
            $customer = Customer::where('phone', $phone)->first();
            if ($customer) return $customer;
        }
        if ($email !== '') {
            $customer = Customer::where('email', strtolower($email))->first();
            if ($customer) return $customer;
        }

        return Customer::create([
            'name' => $validated['customer_name'] ?? null,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? strtolower($email) : null,
            'address' => $validated['customer_address'] ?? null,
            'city' => $validated['city'] ?? null,
            'notes' => 'Created from public order workflow',
        ]);
    }

    public function findOrCreateForAdmin(array $validated): Customer
    {
        $phone = trim((string) ($validated['customer_phone'] ?? ''));
        $email = trim((string) ($validated['customer_email'] ?? ''));
        if ($phone !== '') {
            $customer = Customer::where('phone', $phone)->first();
            if ($customer) {
                $customer->update([
                    'name' => $validated['customer_name'] ?? $customer->name,
                    'email' => $email !== '' ? $email : $customer->email,
                    'address' => $validated['customer_address'] ?? $customer->address,
                    'city' => $validated['city'] ?? $customer->city,
                ]);
                return $customer;
            }
        }
        if ($email !== '') {
            $customer = Customer::where('email', strtolower($email))->first();
            if ($customer) {
                $customer->update([
                    'name' => $validated['customer_name'] ?? $customer->name,
                    'phone' => $phone !== '' ? $phone : $customer->phone,
                    'address' => $validated['customer_address'] ?? $customer->address,
                    'city' => $validated['city'] ?? $customer->city,
                ]);
                return $customer;
            }
        }
        return Customer::create([
            'name' => $validated['customer_name'] ?? null,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? strtolower($email) : null,
            'address' => $validated['customer_address'] ?? null,
            'city' => $validated['city'] ?? null,
            'notes' => 'Created from order workflow',
        ]);
    }
}
