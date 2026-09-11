<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class PermissionService
{
    public static function defaultsForRole(?string $role): array
    {
        $all = array_keys(config('permissions', []));
        if ($role === 'admin') {
            return $all;
        }
        if ($role === 'manager') {
            return [
                'dashboard.view','orders.view','orders.create','orders.edit','orders.delete','orders.prepare',
                'orders.consumption.adjust','orders.consumption.review',
                'customers.view','customers.create','customers.edit','customers.notes',
                'inventory.view','inventory.manage','inventory.count','inventory.request','purchases.view','purchases.manage','sales.view','sales.manage',
                'finance.view','expenses.manage','expenses.create','products.view','products.manage','manufacturing.view','manufacturing.manage',
                'reports.view','audit.view','assets.manage',
            ];
        }
        if ($role === 'employee') {
            return [
                'orders.view','orders.prepare','orders.edit','orders.consumption.adjust',
                'customers.view','customers.notes','inventory.view','inventory.count','inventory.request','expenses.create',
            ];
        }
        return [];
    }

    public static function canViewCosts(?string $role): bool
    {
        return in_array($role, ['admin', 'manager'], true);
    }

    public static function forUser(string $userId, ?string $role): array
    {
        if ($role === 'admin') {
            return self::defaultsForRole('admin');
        }

        $defaults = self::defaultsForRole($role);
        $rows = DB::table('user_permissions')->where('user_id', $userId)->get(['permission','allowed']);
        if ($rows->isEmpty()) return $defaults;

        // Treat stored rows as explicit overrides of role defaults, not as a replacement
        // for the role's operational baseline. This prevents older users from losing
        // newly-required baseline permissions (e.g. order/payment updates).
        $denied = $rows->where('allowed', false)->pluck('permission')->all();
        $allowed = $rows->where('allowed', true)->pluck('permission')->all();
        return array_values(array_unique(array_merge(array_diff($defaults, $denied), $allowed)));
    }
}
