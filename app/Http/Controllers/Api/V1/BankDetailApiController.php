<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BankCode;
use App\Models\BankDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankDetailApiController extends BaseApiController
{
    /**
     * List employee bank and mobile money records.
     */
    public function index(Request $request): JsonResponse
    {
        $query = BankDetail::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('employid', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('bank', 'like', "%{$search}%")
                  ->orWhere('account', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $records = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'Bank details retrieved.');
    }

    /**
     * Store or update bank details for an employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => ['required', 'string'],
            'bank'     => ['nullable', 'string', 'max:100'],
            'account'  => ['nullable', 'string', 'max:50'],
            'code'     => ['nullable', 'string', 'max:20'],
            'branch'   => ['nullable', 'string', 'max:100'],
            'momonum'  => ['nullable', 'string', 'max:20'],
            'momoname' => ['nullable', 'string', 'max:100'],
            'shop'     => ['nullable', 'string', 'max:100'],
            'name'     => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $record = BankDetail::updateOrCreate(
            ['employid' => $request->staff_id],
            [
                'bank'     => $request->bank,
                'account'  => $request->account,
                'code'     => $request->code,
                'branch'   => $request->branch,
                'momonum'  => $request->momonum,
                'momoname' => $request->momoname,
                'shop'     => $request->shop,
                'name'     => $request->name,
            ]
        );

        return $this->sendResponse($record, 'Bank details saved successfully.', 200);
    }

    /**
     * List bank branch codes for mobile selection pickers.
     */
    public function bankCodes(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $query = BankCode::query();

        if ($search) {
            $query->where('bankname', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%");
        }

        $codes = $query->orderBy('bankname')->get(['id', 'bankname', 'branchcodes', 'branch']);

        return $this->sendResponse($codes, 'Bank codes retrieved.');
    }
}
