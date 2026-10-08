<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DB::table('receipts')
            ->select([
                'id',
                'receipt_number',
                'applicant_name',
                'address',
                'contact',
                'position',
                'client',
                'descriptions',
                'status',
                'amount_paid',
                'receipt_date',
                'mode_of_payment',
                'receiving_officer',
                'staffid',
            ]);

        $totalRecords = DB::table('receipts')->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('applicant_name', 'like', "%{$search}%")
                  ->orWhere('contact', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%")
                  ->orWhere('descriptions', 'like', "%{$search}%")
                  ->orWhere('receiving_officer', 'like', "%{$search}%")
                  ->orWhere('mode_of_payment', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Client filter
        if ($client = $request->input('client')) {
            if ($client !== 'all') {
                $query->where('client', $client);
            }
        }

        // Date range filters
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('receipt_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('receipt_date', '<=', $toDate);
        }

        // Stats
        $totalRevenue      = (float) DB::table('receipts')->where('status', '!=', 'Archived')->sum('amount_paid');
        $totalPaidCount    = DB::table('receipts')->where('status', 'Paid')->count();
        $totalPendingCount = DB::table('receipts')->where('status', 'Pending')->count();

        // Sorting
        $sort  = $request->input('sort', 'id');
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'receipt_number', 'applicant_name', 'amount_paid', 'receipt_date', 'status', 'client', 'mode_of_payment'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'id';
        }

        $query->orderBy($sort, $order);

        $receipts = $query->paginate(15)->withQueryString();

        // Clients for dropdown
        $dbClients = Client::where('is_active', 1)->orderBy('client_name')->pluck('client_name')->toArray();
        $defaultClients = ['Twellium', 'Olam Tempering', 'Olam BWH', 'A2'];
        $allClients = array_values(array_unique(array_filter(array_merge($defaultClients, $dbClients))));
        sort($allClients);

        return Inertia::render('Receipts/Index', [
            'receipts' => $receipts,
            'filters'  => [
                'search'    => $search ?? '',
                'status'    => $status ?? 'all',
                'client'    => $client ?? 'all',
                'from_date' => $fromDate ?? '',
                'to_date'   => $toDate ?? '',
                'sort'      => $sort,
                'order'     => $order,
            ],
            'stats' => [
                'total_records'  => $totalRecords,
                'total_revenue'  => $totalRevenue,
                'paid_count'     => $totalPaidCount,
                'pending_count'  => $totalPendingCount,
            ],
            'totalRecords'    => $totalRecords,
            'searchCount'     => $searchCount,
            'clients'         => $allClients,
            'currentOfficer'  => $user ? $user->name : '',
            'flash'           => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'applicant_name'    => ['required', 'string', 'max:100'],
            'address'           => ['required', 'string'],
            'contact'           => ['required', 'string', 'max:50'],
            'position'          => ['required', 'string', 'max:100'],
            'client'            => ['required', 'string', 'max:100'],
            'descriptions'      => ['required'],
            'amount_paid'       => ['required', 'numeric', 'min:0.01'],
            'receipt_date'      => ['required', 'date'],
            'receiving_officer' => ['required', 'string', 'max:100'],
            'mode_of_payment'   => ['required', 'in:Momo,Cash'],
            'status'            => ['nullable', 'in:Pending,Paid,Archived'],
            'initiate_paystack' => ['nullable', 'boolean'],
        ], [
            'applicant_name.required' => 'Applicant name is required.',
            'address.required'        => 'Address is required.',
            'contact.required'        => 'Contact number is required.',
            'position.required'       => 'Position is required.',
            'client.required'         => 'Client is required.',
            'descriptions.required'   => 'Please select at least one description.',
            'amount_paid.required'    => 'Amount paid is required.',
            'amount_paid.min'         => 'Amount paid must be at least 0.01.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to create receipt: ' . implode(' | ', $validator->errors()->all()));
        }

        // Process descriptions
        $descriptions = $request->input('descriptions');
        if (is_array($descriptions)) {
            $descriptions = implode(',', array_filter($descriptions));
        }

        if (empty($descriptions)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please select at least one description.');
        }

        // Generate unique receipt number
        $receiptNumber = 'REC' . time();

        $user = $request->user();
        // Look up staffid from register table by email to satisfy FK constraint
        $staffId = null;
        if ($user) {
            $staffId = $user->staff_id;
            if (empty($staffId)) {
                $staffId = DB::table('register')->where('email', $user->email)->value('staffid');
            }
        }
        if (empty($staffId)) {
            $staffId = 'rep1234';
        }

        $initiatePaystack = (bool) $request->input('initiate_paystack', false);
        // Status is strictly 'Pending' upon creation. Only automatic payment verification can mark it 'Paid'.
        $status = 'Pending';

        $applicantName = trim($request->input('applicant_name'));
        $amountPaid    = $request->input('amount_paid');
        $contact       = trim($request->input('contact'));

        DB::table('receipts')->insert([
            'receipt_number'    => $receiptNumber,
            'applicant_name'    => $applicantName,
            'address'           => trim($request->input('address')),
            'contact'           => $contact,
            'position'          => trim($request->input('position')),
            'client'            => trim($request->input('client')),
            'descriptions'      => $descriptions,
            'amount_paid'       => $amountPaid,
            'receiving_officer' => trim($request->input('receiving_officer')),
            'mode_of_payment'   => $request->input('mode_of_payment', 'Momo'),
            'receipt_date'      => $request->input('receipt_date', date('Y-m-d')),
            'staffid'           => $staffId,
            'status'            => $status,
        ]);

        if ($initiatePaystack) {
            return $this->processPaystackInitialization($receiptNumber, $applicantName, $amountPaid, $contact, $user ? $user->email : null);
        }

        return redirect()->back()
            ->with('success', "Receipt #{$receiptNumber} created successfully for {$applicantName}.");
    }

    public function initiatePayment(Request $request, $id)
    {
        $receipt = DB::table('receipts')->where('id', $id)->first();
        if (!$receipt) {
            return redirect()->back()->with('error', 'Receipt not found.');
        }

        $user = $request->user();
        return $this->processPaystackInitialization(
            $receipt->receipt_number,
            $receipt->applicant_name,
            $receipt->amount_paid,
            $receipt->contact,
            $user ? $user->email : null
        );
    }

    public function verifyPayment(Request $request)
    {
        $reference = $request->query('reference', $request->query('trxref'));

        if (!$reference) {
            return redirect()->route('receipts.index')->with('error', 'No transaction reference supplied for verification.');
        }

        $secretKey = (string) config('services.paystack.secret_key');
        if (empty($secretKey)) {
            return redirect()->route('receipts.index')->with('error', 'Paystack Secret Key is not configured in environment variables.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . trim($secretKey),
                'Cache-Control' => 'no-cache',
            ])->get('https://api.paystack.co/transaction/verify/' . urlencode($reference));

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && $result['status'] === true && isset($result['data']['status']) && $result['data']['status'] === 'success') {
                $receipt = DB::table('receipts')->where('receipt_number', $reference)->first();

                if ($receipt) {
                    DB::table('receipts')
                        ->where('id', $receipt->id)
                        ->update([
                            'status'          => 'Paid',
                            'mode_of_payment' => 'Momo',
                        ]);
                    $receiptId = $receipt->id;
                } else {
                    // Check if pending receipt exists in session as fallback
                    $pendingData = session('pending_receipt');
                    if ($pendingData) {
                        $user = $request->user();
                        $staffId = null;
                        if ($user) {
                            $staffId = $user->staff_id;
                            if (empty($staffId)) {
                                $staffId = DB::table('register')->where('email', $user->email)->value('staffid');
                            }
                        }
                        if (empty($staffId)) {
                            $staffId = 'rep1234';
                        }

                        $receiptId = DB::table('receipts')->insertGetId([
                            'receipt_number'    => $reference,
                            'applicant_name'    => $pendingData['applicant_name'] ?? 'N/A',
                            'address'           => $pendingData['address'] ?? 'N/A',
                            'contact'           => $pendingData['contact'] ?? 'N/A',
                            'position'          => $pendingData['position'] ?? 'N/A',
                            'client'            => $pendingData['client'] ?? 'N/A',
                            'descriptions'      => $pendingData['descriptions'] ?? 'Forms,Medicals,Police reports',
                            'amount_paid'       => $pendingData['amount_paid'] ?? 0,
                            'receiving_officer' => $pendingData['receiving_officer'] ?? 'System',
                            'mode_of_payment'   => 'Momo',
                            'receipt_date'      => $pendingData['receipt_date'] ?? date('Y-m-d'),
                            'staffid'           => $staffId,
                            'status'            => 'Paid',
                        ]);

                        session()->forget('pending_receipt');
                    } else {
                        return redirect()->route('receipts.index')->with('success', "Payment verified for reference #{$reference}, but matching receipt record was not found.");
                    }
                }

                return redirect()->route('receipts.show', $receiptId)->with('success', "Payment verified successfully! Receipt #{$reference} marked as Paid.");
            }

            $errorMsg = $result['message'] ?? 'Payment verification failed or was cancelled.';
            return redirect()->route('receipts.index')->with('error', "Paystack Verification Error: {$errorMsg}");
        } catch (\Exception $e) {
            Log::error('Paystack Verification Exception: ' . $e->getMessage());
            return redirect()->route('receipts.index')->with('error', 'Paystack Connection Error: ' . $e->getMessage());
        }
    }

    private function processPaystackInitialization(string $receiptNumber, string $applicantName, $amountPaid, string $contact, ?string $userEmail)
    {
        $secretKey = (string) config('services.paystack.secret_key');

        $rawAmount = str_replace([',', 'GHS', '¢'], '', (string)$amountPaid);
        $amountInPesewas = intval(round(floatval($rawAmount) * 100));

        if ($amountInPesewas <= 0) {
            return redirect()->back()->with('error', 'Invalid amount paid for Paystack payment.');
        }

        $email = filter_var($userEmail, FILTER_VALIDATE_EMAIL) ? $userEmail : 'billing@sbpafrica.group';

        $payload = [
            'amount'       => $amountInPesewas,
            'email'        => $email,
            'currency'     => 'GHS',
            'reference'    => $receiptNumber,
            'callback_url' => route('receipts.verify-payment'),
            'metadata'     => [
                'mobile_number' => $contact,
                'custom_fields' => [
                    [
                        'display_name'  => 'Applicant Name',
                        'variable_name' => 'applicant_name',
                        'value'         => $applicantName,
                    ]
                ]
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . trim($secretKey),
                'Content-Type'  => 'application/json',
                'Cache-Control' => 'no-cache',
            ])->post('https://api.paystack.co/transaction/initialize', $payload);

            $result = $response->json();

            if ($response->successful() && isset($result['status']) && $result['status'] === true && isset($result['data']['authorization_url'])) {
                $authUrl = $result['data']['authorization_url'];
                return Inertia::location($authUrl);
            }

            $errorMsg = $result['message'] ?? 'Failed to initialize Paystack payment.';
            return redirect()->back()->with('error', "Paystack API Error: {$errorMsg}");
        } catch (\Exception $e) {
            Log::error('Paystack Initiation Exception: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Paystack Connection Error: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $receipt = DB::table('receipts')->where('id', $id)->first();
        if (!$receipt) {
            return redirect()->back()->with('error', 'Receipt not found.');
        }

        $validator = Validator::make($request->all(), [
            'receipt_number'    => ['required', 'string', 'max:20', Rule::unique('receipts')->ignore($id)],
            'applicant_name'    => ['required', 'string', 'max:100'],
            'address'           => ['required', 'string'],
            'contact'           => ['required', 'string', 'max:50'],
            'position'          => ['required', 'string', 'max:100'],
            'client'            => ['required', 'string', 'max:100'],
            'descriptions'      => ['required'],
            'amount_paid'       => ['required', 'numeric', 'min:0.01'],
            'receipt_date'      => ['required', 'date'],
            'receiving_officer' => ['required', 'string', 'max:100'],
            'mode_of_payment'   => ['required', 'in:Momo,Cash'],
            'status'            => ['nullable', 'in:Pending,Paid,Archived'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Failed to update receipt: ' . implode(' | ', $validator->errors()->all()));
        }

        // Process descriptions
        $descriptions = $request->input('descriptions');
        if (is_array($descriptions)) {
            $descriptions = implode(',', array_filter($descriptions));
        }

        if (empty($descriptions)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please select at least one description.');
        }

        DB::table('receipts')->where('id', $id)->update([
            'receipt_number'    => trim($request->input('receipt_number')),
            'applicant_name'    => trim($request->input('applicant_name')),
            'address'           => trim($request->input('address')),
            'contact'           => trim($request->input('contact')),
            'position'          => trim($request->input('position')),
            'client'            => trim($request->input('client')),
            'descriptions'      => $descriptions,
            'amount_paid'       => $request->input('amount_paid'),
            'receiving_officer' => trim($request->input('receiving_officer')),
            'mode_of_payment'   => $request->input('mode_of_payment'),
            'receipt_date'      => $request->input('receipt_date'),
            // Status remains untouched — only payment verification can update status
            'status'            => $receipt->status,
        ]);

        return redirect()->back()
            ->with('success', "Receipt #{$request->input('receipt_number')} updated successfully.");
    }

    public function destroy($id)
    {
        $receipt = DB::table('receipts')->where('id', $id)->first();
        if (!$receipt) {
            return redirect()->back()->with('error', 'Receipt not found.');
        }

        $number = $receipt->receipt_number;

        DB::transaction(function () use ($id) {
            DB::table('tracking_logs')->where('receipt_id', $id)->delete();
            DB::table('receipts')->where('id', $id)->delete();
        });

        return redirect()->back()
            ->with('success', "Receipt #{$number} deleted successfully.");
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No receipts selected for deletion.');
        }

        $deletedCount = 0;
        DB::transaction(function () use ($ids, &$deletedCount) {
            DB::table('tracking_logs')->whereIn('receipt_id', $ids)->delete();
            $deletedCount = DB::table('receipts')->whereIn('id', $ids)->delete();
        });

        return redirect()->back()
            ->with('success', "Successfully deleted {$deletedCount} receipt(s).");
    }

    public function export(Request $request)
    {
        $query = DB::table('receipts')
            ->select([
                'id',
                'receipt_number',
                'applicant_name',
                'address',
                'contact',
                'position',
                'client',
                'descriptions',
                'amount_paid',
                'receipt_date',
                'mode_of_payment',
                'receiving_officer',
                'staffid',
                'status',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhere('applicant_name', 'like', "%{$search}%")
                  ->orWhere('contact', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%")
                  ->orWhere('descriptions', 'like', "%{$search}%")
                  ->orWhere('receiving_officer', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($client = $request->input('client')) {
            if ($client !== 'all') {
                $query->where('client', $client);
            }
        }

        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('receipt_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('receipt_date', '<=', $toDate);
        }

        $records = $query->orderBy('id', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Receipts');

        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Receipt Number');
        $sheet->setCellValue('C1', 'Applicant Name');
        $sheet->setCellValue('D1', 'Address');
        $sheet->setCellValue('E1', 'Contact');
        $sheet->setCellValue('F1', 'Position');
        $sheet->setCellValue('G1', 'Client');
        $sheet->setCellValue('H1', 'Descriptions');
        $sheet->setCellValue('I1', 'Amount Paid (GHS)');
        $sheet->setCellValue('J1', 'Receipt Date');
        $sheet->setCellValue('K1', 'Mode of Payment');
        $sheet->setCellValue('L1', 'Receiving Officer');
        $sheet->setCellValue('M1', 'Staff ID');
        $sheet->setCellValue('N1', 'Status');

        $rowNum = 2;
        foreach ($records as $item) {
            $sheet->setCellValue('A' . $rowNum, $item->id);
            $sheet->setCellValue('B' . $rowNum, $item->receipt_number);
            $sheet->setCellValue('C' . $rowNum, $item->applicant_name);
            $sheet->setCellValue('D' . $rowNum, $item->address);
            $sheet->setCellValue('E' . $rowNum, $item->contact);
            $sheet->setCellValue('F' . $rowNum, $item->position);
            $sheet->setCellValue('G' . $rowNum, $item->client);
            $sheet->setCellValue('H' . $rowNum, $item->descriptions);
            $sheet->setCellValue('I' . $rowNum, (float) $item->amount_paid);
            $sheet->setCellValue('J' . $rowNum, $item->receipt_date);
            $sheet->setCellValue('K' . $rowNum, $item->mode_of_payment);
            $sheet->setCellValue('L' . $rowNum, $item->receiving_officer);
            $sheet->setCellValue('M' . $rowNum, $item->staffid);
            $sheet->setCellValue('N' . $rowNum, $item->status);
            $rowNum++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Receipts_' . date('Y_m_d_H_i_s') . '.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show($id)
    {
        $receipt = DB::table('receipts')->where('id', $id)->orWhere('receipt_number', $id)->first();

        if (!$receipt) {
            return redirect()->route('receipts.index')->with('error', 'Receipt not found.');
        }

        return Inertia::render('Receipts/Show', [
            'receipt' => $receipt,
        ]);
    }
}
