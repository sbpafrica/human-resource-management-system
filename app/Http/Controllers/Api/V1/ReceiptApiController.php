<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReceiptApiController extends BaseApiController
{
    /**
     * List all receipts with search and status filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Receipt::query()->latest('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('applicant_name', 'like', "%{$search}%")
                  ->orWhere('staffid', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($client = $request->input('client')) {
            $query->where('client', $client);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $receipts = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => $receipts->items(),
            'current_page' => $receipts->currentPage(),
            'total_pages'  => $receipts->lastPage(),
            'total_items'  => $receipts->total(),
        ], 'Receipts retrieved successfully.');
    }

    /**
     * Create a new receipt.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'applicant_name'    => ['required', 'string', 'max:255'],
            'amount_paid'       => ['required', 'numeric', 'min:0'],
            'address'           => ['nullable', 'string', 'max:255'],
            'contact'           => ['nullable', 'string', 'max:20'],
            'position'          => ['nullable', 'string', 'max:100'],
            'client'            => ['nullable', 'string', 'max:100'],
            'descriptions'      => ['nullable', 'string'],
            'mode_of_payment'   => ['nullable', 'string', 'max:50'],
            'receipt_date'      => ['nullable', 'date'],
            'staffid'           => ['nullable', 'string', 'max:50'],
            'receiving_officer' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $receiptNumber = Receipt::generateUniqueReceiptNumber();

        $receipt = Receipt::create([
            'receipt_number'    => $receiptNumber,
            'applicant_name'    => $request->applicant_name,
            'address'           => $request->address,
            'contact'           => $request->contact,
            'position'          => $request->position,
            'client'            => $request->client,
            'descriptions'      => $request->descriptions,
            'amount_paid'       => $request->amount_paid,
            'receiving_officer' => $request->receiving_officer ?? $request->user()?->name,
            'mode_of_payment'   => $request->mode_of_payment ?? 'Cash',
            'receipt_date'      => $request->receipt_date ?? now()->toDateString(),
            'staffid'           => $request->staffid,
            'status'            => 'completed',
        ]);

        return $this->sendResponse($receipt, 'Receipt created successfully.', 201);
    }

    /**
     * Show single receipt details.
     */
    public function show($id): JsonResponse
    {
        $receipt = Receipt::find($id);

        if (!$receipt) {
            return $this->sendError('Receipt not found.', null, 404);
        }

        return $this->sendResponse($receipt, 'Receipt details retrieved.');
    }

    /**
     * Update an existing receipt.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $receipt = Receipt::find($id);

        if (!$receipt) {
            return $this->sendError('Receipt not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'applicant_name'  => ['sometimes', 'string', 'max:255'],
            'amount_paid'     => ['sometimes', 'numeric', 'min:0'],
            'address'         => ['nullable', 'string', 'max:255'],
            'contact'         => ['nullable', 'string', 'max:20'],
            'descriptions'    => ['nullable', 'string'],
            'mode_of_payment' => ['nullable', 'string'],
            'status'          => ['nullable', 'string', 'in:completed,pending,cancelled'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $receipt->update($request->only([
            'applicant_name',
            'amount_paid',
            'address',
            'contact',
            'descriptions',
            'mode_of_payment',
            'status',
        ]));

        return $this->sendResponse($receipt, 'Receipt updated successfully.');
    }

    /**
     * Delete receipt.
     */
    public function destroy($id): JsonResponse
    {
        $receipt = Receipt::find($id);

        if (!$receipt) {
            return $this->sendError('Receipt not found.', null, 404);
        }

        $receipt->delete();

        return $this->sendResponse(null, 'Receipt deleted successfully.');
    }

    /**
     * Initiate Paystack Mobile Payment for a receipt.
     */
    public function initiatePayment(Request $request, $id): JsonResponse
    {
        $receipt = Receipt::find($id);

        if (!$receipt) {
            return $this->sendError('Receipt not found.', null, 404);
        }

        $secretKey = config('services.paystack.secret_key');
        if (empty($secretKey)) {
            return $this->sendError('Paystack API is not configured on server.', null, 503);
        }

        $email = $request->input('email', $request->user()?->email ?? 'customer@sbpafrica.group');
        $amountInPesewas = (int) round($receipt->amount_paid * 100);

        try {
            $response = Http::withToken($secretKey)
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email'        => $email,
                    'amount'       => $amountInPesewas,
                    'currency'     => 'GHS',
                    'reference'    => 'REC_' . $receipt->id . '_' . time(),
                    'callback_url' => url('/api/v1/receipts/verify-payment'),
                    'metadata'     => [
                        'receipt_id'     => $receipt->id,
                        'receipt_number' => $receipt->receipt_number,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json()['data'];
                $receipt->paystack_reference = $data['reference'];
                $receipt->status = 'pending';
                $receipt->save();

                return $this->sendResponse([
                    'authorization_url' => $data['authorization_url'],
                    'access_code'       => $data['access_code'],
                    'reference'         => $data['reference'],
                ], 'Payment authorization URL generated.');
            }

            return $this->sendError('Failed to initiate payment with Paystack.', $response->json(), 400);
        } catch (\Throwable $e) {
            return $this->sendError('Paystack error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Verify Paystack payment reference.
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return $this->sendError('Payment reference is required.', null, 400);
        }

        $secretKey = config('services.paystack.secret_key');
        if (empty($secretKey)) {
            return $this->sendError('Paystack not configured.', null, 503);
        }

        try {
            $response = Http::withToken($secretKey)
                ->get("https://api.paystack.co/transaction/verify/{$reference}");

            if ($response->successful() && $response->json('data.status') === 'success') {
                $meta = $response->json('data.metadata');
                $receiptId = $meta['receipt_id'] ?? null;

                if ($receiptId) {
                    $receipt = Receipt::find($receiptId);
                    if ($receipt) {
                        $receipt->status = 'completed';
                        $receipt->save();
                    }
                }

                return $this->sendResponse($response->json('data'), 'Payment verified successfully.');
            }

            return $this->sendError('Payment verification failed or status is not success.', $response->json(), 400);
        } catch (\Throwable $e) {
            return $this->sendError('Paystack verification error: ' . $e->getMessage(), null, 500);
        }
    }
}
