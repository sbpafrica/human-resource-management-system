<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use App\Models\MonthlySalary;
use App\Models\Ssnit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SsnitPaymentApiController extends BaseApiController
{
    /**
     * Get monthly SSNIT payment schedule for a client company.
     */
    public function index(Request $request): JsonResponse
    {
        $clientDb = $request->input('client');
        $month    = $request->input('month', now()->format('Y-m'));

        if (!$clientDb) {
            // Return active clients list for SSNIT
            $clients = Client::where('is_active', true)
                ->orderBy('display_order')
                ->get(['id', 'client_name', 'short_name', 'client_dbname', 'slug']);

            return $this->sendResponse([
                'month'   => $month,
                'clients' => $clients,
            ], 'Active SSNIT client entities.');
        }

        $tableName = MonthlySalary::generateTableName($clientDb, $month);

        if (!Schema::hasTable($tableName)) {
            return $this->sendResponse([
                'client'       => $clientDb,
                'month'        => $month,
                'records'      => [],
                'total_items'  => 0,
                'note'         => "No payroll data computed for {$clientDb} in {$month}.",
            ], 'No SSNIT records found for period.');
        }

        $records = DB::table("{$tableName} as p")
            ->leftJoin('ssnit as s', 'p.staffida', '=', 's.staffid')
            ->select([
                'p.staffida as staff_id',
                DB::raw("COALESCE(s.lastname, p.name) as employee_name"),
                's.ssnit_number',
            ])
            ->paginate(50);

        return $this->sendResponse([
            'client'       => $clientDb,
            'month'        => $month,
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'SSNIT payment schedule retrieved.');
    }

    /**
     * List employee master SSNIT records.
     */
    public function masterRecords(Request $request): JsonResponse
    {
        $query = Ssnit::query();

        if ($search = $request->input('search')) {
            $query->where('staffid', 'like', "%{$search}%")
                  ->orWhere('ssnit_number', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $records = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'Master SSNIT records retrieved.');
    }

    /**
     * Add or update employee SSNIT registration details.
     */
    public function storeMasterRecord(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id'     => ['required', 'string'],
            'ssnit_number' => ['required', 'string', 'max:50'],
            'shop'         => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $record = Ssnit::updateOrCreate(
            ['staffid' => $request->staff_id],
            [
                'ssnit_number' => $request->ssnit_number,
                'shop'         => $request->shop,
            ]
        );

        return $this->sendResponse($record, 'SSNIT record saved successfully.', 200);
    }
}
