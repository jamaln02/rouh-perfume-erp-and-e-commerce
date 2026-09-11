<?php
namespace App\Http\Controllers;
use App\Models\OpeningBalance;
use Illuminate\Http\JsonResponse;
class AppConfigController { public function __invoke(): JsonResponse { $date=(string)(OpeningBalance::query()->orderBy('opening_balance_date')->value('opening_balance_date') ?? ''); return response()->json(['accounting_start_date'=>$date!==''?$date:null]); } }
