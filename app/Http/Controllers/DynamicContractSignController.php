<?php

namespace App\Http\Controllers;

use App\Services\Contract\DynamicContractResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DynamicContractSignController extends Controller
{
    protected DynamicContractResolver $resolver;

    public function __construct(DynamicContractResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Show interactive contract viewing & signing page.
     */
    public function showSignPage(Request $request, $staffId = null)
    {
        $staffId = trim($staffId ?? $request->query('staff_id', ''));
        $preferredType = $request->query('type'); // 'contract' or 'renewal'

        if ($staffId === '') {
            return view('contracts.sign', [
                'contract' => null,
                'staffId'  => null,
                'error'    => null,
            ]);
        }

        $contract = $this->resolver->resolve($staffId, $preferredType);

        if (!$contract) {
            return view('contracts.sign', [
                'contract' => null,
                'staffId'  => $staffId,
                'error'    => "No active contract or renewal found for Staff ID: {$staffId}. Please verify your ID or contact HR.",
            ]);
        }

        return view('contracts.sign', [
            'contract' => $contract,
            'staffId'  => $staffId,
            'error'    => null,
        ]);
    }

    /**
     * Submit digital signature via web form.
     */
    public function submitSignature(Request $request, $staffId)
    {
        $validator = Validator::make($request->all(), [
            'signature_data' => 'required|string',
            'contract_type'  => 'nullable|string|in:contract,renewal',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid signature before submitting.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $updated = $this->resolver->saveSignature(
                $staffId,
                $request->input('signature_data'),
                $request->input('contract_type')
            );

            return response()->json([
                'success'      => true,
                'message'      => 'Your contract has been signed and activated successfully!',
                'contract'     => $updated,
                'download_url' => route('contracts.public.download', ['staff_id' => $staffId]),
                'preview_url'  => route('contracts.public.preview', ['staff_id' => $staffId]),
            ]);
        } catch (\Exception $e) {
            Log::error("Contract signature submission failed for Staff ID {$staffId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save signature: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream PDF preview inline.
     */
    public function previewPdf(Request $request, $staffId)
    {
        $preferredType = $request->query('type');
        $contract = $this->resolver->resolve($staffId, $preferredType);

        if (!$contract) {
            abort(404, "Contract not found for Staff ID: {$staffId}");
        }

        $pdf = $this->resolver->generatePdf($contract);
        $clientName = str_replace(' ', '_', $contract['client']);
        $filename = "Contract_{$clientName}_{$staffId}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Download contract PDF.
     */
    public function downloadPdf(Request $request, $staffId)
    {
        $preferredType = $request->query('type');
        $contract = $this->resolver->resolve($staffId, $preferredType);

        if (!$contract) {
            abort(404, "Contract not found for Staff ID: {$staffId}");
        }

        $pdf = $this->resolver->generatePdf($contract);
        $clientName = str_replace(' ', '_', $contract['client']);
        $filename = "Contract_{$clientName}_{$staffId}.pdf";

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REST API Endpoints for staff.sbpafrica.group
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * API: Get contract details by staff ID.
     */
    public function apiGetContract(Request $request, $staffId)
    {
        $preferredType = $request->query('type');
        $contract = $this->resolver->resolve($staffId, $preferredType);

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => "Contract not found for Staff ID: {$staffId}",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'staff_id'       => $contract['staff_id'],
                'employee_name'  => $contract['employee_name'],
                'client'         => $contract['client'],
                'contract_type'  => $contract['contract_type'],
                'table'          => $contract['table'],
                'is_signed'      => $contract['is_signed'],
                'signature_url'  => $contract['signature'] ? url('images/signatures/' . $contract['signature']) : null,
                'signature_date' => $contract['signature_date'],
                'sign_url'       => route('contracts.public.sign', ['staff_id' => $staffId]),
                'download_url'   => route('contracts.public.download', ['staff_id' => $staffId]),
                'preview_url'    => route('contracts.public.preview', ['staff_id' => $staffId]),
                'details'        => $contract['view_data'],
            ],
        ]);
    }

    /**
     * API: Sign contract via REST API.
     */
    public function apiSignContract(Request $request, $staffId)
    {
        $validator = Validator::make($request->all(), [
            'signature_data' => 'required|string',
            'contract_type'  => 'nullable|string|in:contract,renewal',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $updated = $this->resolver->saveSignature(
                $staffId,
                $request->input('signature_data'),
                $request->input('contract_type')
            );

            return response()->json([
                'success' => true,
                'message' => 'Contract signed and activated successfully.',
                'data'    => [
                    'staff_id'       => $updated['staff_id'],
                    'employee_name'  => $updated['employee_name'],
                    'client'         => $updated['client'],
                    'contract_type'  => $updated['contract_type'],
                    'is_signed'      => true,
                    'signature_url'  => $updated['signature'] ? url('images/signatures/' . $updated['signature']) : null,
                    'signature_date' => $updated['signature_date'],
                    'download_url'   => route('contracts.public.download', ['staff_id' => $staffId]),
                    'preview_url'    => route('contracts.public.preview', ['staff_id' => $staffId]),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("API contract signing failed for Staff ID {$staffId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
