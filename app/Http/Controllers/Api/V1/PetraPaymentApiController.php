<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use App\Models\MonthlySalary;
use App\Models\Petra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PetraPaymentApiController extends BaseApiController
{
    /**
     * Get monthly Petra Provident Fund payment schedule for a client company.
     */
    public function index(Request $request): JsonResponse
    {
        $clientDb = $request->input('client');
        $month    = $request->input('month', now()->format('Y-m'));

        if (!$clientDb) {
            $clients = Client::where('is_active', true)
                ->orderBy('display_order')
                ->get(['id', 'client_name', 'short_name', 'client_dbname', 'slug']);

            return $this->sendResponse([
                'month'   => $month,
                'clients' => $clients,
            ], 'Active Petra client entities.');
        }

        $tableName = MonthlySalary::generateTableName($clientDb, $month);

        if (!Schema::hasTable($tableName)) {
            return $this->sendResponse([
                'client'      => $clientDb,
                'month'       => $month,
                'records'     => [],
                'total_items' => 0,
                'note'        => "No payroll data computed for {$clientDb} in {$month}.",
            ], 'No Petra records found for period.');
        }

        $records = DB::table("{$tableName} as p")
            ->leftJoin('petraorg as pt', 'p.staffida', '=', 'pt.staffid')
            ->select([
                'p.staffida as staff_id',
                DB::raw("COALESCE(pt.lastname, p.name) as employee_name"),
                'pt.petra_number',
            ])
            ->paginate(50);

        return $this->sendResponse([
            'client'       => $clientDb,
            'month'        => $month,
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'Petra payment schedule retrieved.');
    }

    /**
     * List employee master Petra records.
     */
    public function masterRecords(Request $request): JsonResponse
    {
        $query = Petra::query();

        if ($search = $request->input('search')) {
            $query->where('staffid', 'like', "%{$search}%")
                  ->orWhere('petra_number', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $records = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'Master Petra records retrieved.');
    }

    /**
     * Add or update employee Petra registration details.
     */
    public function storeMasterRecord(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id'     => ['required', 'string'],
            'petra_number' => ['required', 'string', 'max:50'],
            'shop'         => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $record = Petra::updateOrCreate(
            ['staffid' => $request->staff_id],
            [
                'petra_number' => $request->petra_number,
                'shop'         => $request->shop,
            ]
        );

        return $this->sendResponse($record, 'Petra record saved successfully.', 200);
    }
}
