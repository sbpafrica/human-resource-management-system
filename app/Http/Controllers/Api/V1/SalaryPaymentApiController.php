<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use App\Models\MonthlySalary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalaryPaymentApiController extends BaseApiController
{
    /**
     * Retrieve salary distribution schedule (Bank & MoMo) for a client company.
     */
    public function index(Request $request): JsonResponse
    {
        $clientDb = $request->input('client');
        $month    = $request->input('month', now()->format('Y-m'));
        $search   = $request->input('search');

        if (!$clientDb) {
            $clients = Client::where('is_active', true)
                ->orderBy('display_order')
                ->get(['id', 'client_name', 'short_name', 'client_dbname', 'slug']);

            return $this->sendResponse([
                'month'   => $month,
                'clients' => $clients,
            ], 'Active salary payment client entities.');
        }

        $tableName = MonthlySalary::generateTableName($clientDb, $month);

        if (!Schema::hasTable($tableName)) {
            return $this->sendResponse([
                'client'      => $clientDb,
                'month'       => $month,
                'records'     => [],
                'total_items' => 0,
                'note'        => "No payroll salary data found for {$clientDb} in {$month}.",
            ], 'No salary payment records for period.');
        }

        $query = DB::table("{$tableName} as ms")
            ->leftJoin('bankdetails as bd', 'ms.staffida', '=', 'bd.employid')
            ->select([
                'ms.staffida as staff_id',
                'ms.name as employee_name',
                'bd.shop',
                'bd.bank',
                'bd.account as account_number',
                'bd.code as sorting_code',
                'bd.branch',
                'bd.momonum as momo_number',
                'bd.momoname as momo_name',
                DB::raw("COALESCE(ms.netpay, 0) as net_pay"),
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ms.staffida', 'like', "%{$search}%")
                  ->orWhere('ms.name', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%")
                  ->orWhere('bd.account', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $records = $query->paginate($perPage);

        return $this->sendResponse([
            'client'       => $clientDb,
            'month'        => $month,
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'Salary payment distribution schedule retrieved.');
    }
}
