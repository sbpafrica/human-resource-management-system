<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\DocumentIntelligence\DocumentOcrService;
use App\Models\Employee;
use App\Models\BankDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class DocumentIntelligenceController extends Controller
{
    public function __construct(
        protected DocumentOcrService $ocrService
    ) {}

    /**
     * Scans and extracts structured fields from uploaded ID/Contract/Bank documents.
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,webp,pdf', 'max:10240'],
            'document_type' => ['nullable', 'string', 'in:ghana_card,bank_document,ssnit_card,contract,auto'],
        ]);

        $file = $request->file('file');
        $docType = $request->input('document_type');
        if ($docType === 'auto') {
            $docType = null;
        }

        $result = $this->ocrService->parseDocument($file, $docType);

        return response()->json($result);
    }

    /**
     * 1-Click Saves verified extracted fields directly into Employee Master tables.
     */
    public function saveToEmployee(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => ['required', 'string'],
            'employeename' => ['required', 'string'],
            'ghcard' => ['nullable', 'string'],
            'dateofbirth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string'],
            'nationality' => ['nullable', 'string'],
            'company' => ['nullable', 'string'],
            'position' => ['nullable', 'string'],
            'shop' => ['nullable', 'string'],
            'startdate' => ['nullable', 'date'],
            'phone1' => ['nullable', 'string'],
            'bank' => ['nullable', 'string'],
            'account' => ['nullable', 'string'],
            'branch' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();

            $staffId = trim($request->input('staff_id'));

            // Upsert newemployeedata
            $employee = Employee::updateOrCreate(
                ['staffid' => $staffId],
                array_filter([
                    'employeename' => $request->input('employeename'),
                    'ghcard' => $request->input('ghcard'),
                    'dateofbirth' => $request->input('dateofbirth'),
                    'gender' => $request->input('gender'),
                    'nationality' => $request->input('nationality', 'Ghanaian'),
                    'company' => $request->input('company'),
                    'position' => $request->input('position'),
                    'shop' => $request->input('shop'),
                    'startdate' => $request->input('startdate'),
                    'phone1' => $request->input('phone1'),
                ], fn($val) => $val !== null)
            );

            // Upsert bankdetails if provided
            if ($request->filled('account')) {
                BankDetail::updateOrCreate(
                    ['employid' => $staffId],
                    array_filter([
                        'name' => $request->input('employeename'),
                        'bank' => $request->input('bank'),
                        'account' => $request->input('account'),
                        'branch' => $request->input('branch'),
                        'shop' => $request->input('shop') ?: $request->input('position'),
                    ], fn($val) => $val !== null)
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Employee record for {$employee->employeename} ({$staffId}) saved successfully.",
                'employee' => $employee,
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to save employee data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
