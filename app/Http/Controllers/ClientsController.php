<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class ClientsController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query();
        $totalRecords = Client::count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                  ->orWhere('client_dbname', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'display_order');
        $order = $request->input('order', 'asc');

        if (!in_array($sort, ['client_name', 'client_dbname', 'short_name', 'display_order', 'is_active', 'color'])) {
            $sort = 'display_order';
        }

        $query->orderBy($sort, $order);
        $clients = $query->paginate(10)->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search ?? '',
                'sort' => $sort,
                'order' => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        try {
            Log::info('ClientsController@store', $request->all());

            $rules = [
                'client_name' => ['required', 'string', 'max:150', 'no_french_accents'],
                'client_dbname' => ['required', 'string', 'max:150', 'unique:client,client_dbname', 'no_french_accents'],
                'short_name' => ['nullable', 'string', 'max:50'],
                'color' => ['required', 'string', 'regex:/^#[a-f0-9]{6}$/i'],
                'display_order' => ['nullable', 'integer', 'min:0'],
                'is_active' => ['required', 'boolean'],
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->withInput()
                    ->with('error', 'Validation failed: ' . implode(' | ', $validator->errors()->all()));
            }

            $data = $request->only([
                'client_name', 'client_dbname', 'short_name', 'color', 'is_active'
            ]);

            $data['slug'] = Str::slug($data['client_name']);
            $data['display_order'] = $request->filled('display_order')
                ? $request->display_order
                : Client::max('display_order') + 1;

            Client::create($data);

            return redirect()->route('clients.index')->with('success', 'Client created successfully!');

        } catch (\Exception $e) {
            Log::error('ClientsController@store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Failed to create client.');
        }
    }

   // app/Http/Controllers/ClientsController.php
public function update(Request $request, Client $client)  // ← Keep model binding (now works!)
{
    try {
        Log::info('ClientsController@update', ['id' => $client->id, 'data' => $request->all()]);

        $rules = [
            'client_name' => ['required', 'string', 'max:150', 'no_french_accents'],
            'client_dbname' => [
                'required', 'string', 'max:150',
                Rule::unique('client', 'client_dbname')->ignore($client->id, 'id'), // ← CORRECT
                'no_french_accents'
            ],
            'short_name' => ['nullable', 'string', 'max:50'],
            'color' => ['required', 'string', 'regex:/^#[a-f0-9]{6}$/i'],
            'display_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Update failed: ' . implode(' | ', $validator->errors()->all()));
        }

        $data = $request->only([
            'client_name', 'client_dbname', 'short_name', 'color', 'display_order', 'is_active'
        ]);

        $data['slug'] = Str::slug($data['client_name']);

        $client->update($data);

        return redirect()->route('clients.index')->with('success', 'Client updated successfully!');

    } catch (\Exception $e) {
        Log::error('ClientsController@update error', [
            'id' => $client->id ?? 'unknown',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return redirect()->back()->with('error', 'Failed to update client.');
    }
}

    public function destroy(Client $client)
    {
        try {
            Log::info('ClientsController@destroy', ['id' => $client->id]);
            $client->delete();
            return redirect()->route('clients.index')->with('success', 'Client deleted.');
        } catch (\Exception $e) {
            Log::error('ClientsController@destroy error', [
                'id' => $client->id,
                'message' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', 'Failed to delete client.');
        }
    }

    public function bulkDestroy(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'integer|exists:client,id'
            ]);

            $count = Client::whereIn('id', $request->ids)->delete();

            return redirect()->route('clients.index')
                ->with('success', "Deleted $count client(s) successfully.");

        } catch (\Exception $e) {
            Log::error('ClientsController@bulkDestroy error', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Bulk delete failed.');
        }
    }

    public function export(Request $request)
    {
        try {
            $query = Client::query();

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('client_name', 'like', "%{$search}%")
                      ->orWhere('client_dbname', 'like', "%{$search}%")
                      ->orWhere('short_name', 'like', "%{$search}%");
                });
            }

            $sort = $request->input('sort', 'display_order');
            $order = $request->input('order', 'asc');
            $query->orderBy($sort, $order);

            $clients = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Headers
            $sheet->setCellValue('A1', 'Client Name');
            $sheet->setCellValue('B1', 'DB Name');
            $sheet->setCellValue('C1', 'Short Name');
            $sheet->setCellValue('D1', 'Color');
            $sheet->setCellValue('E1', 'Display Order');
            $sheet->setCellValue('F1', 'Active');

            $row = 2;
            foreach ($clients as $client) {
                $sheet->setCellValue('A' . $row, $client->client_name);
                $sheet->setCellValue('B' . $row, $client->client_dbname);
                $sheet->setCellValue('C' . $row, $client->short_name);
                $sheet->setCellValue('D' . $row, $client->color);
                $sheet->setCellValue('E' . $row, $client->display_order);
                $sheet->setCellValue('F' . $row, $client->is_active ? 'Yes' : 'No');
                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'clients_' . now()->format('Y-m-d') . '.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Export failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Export failed.');
        }
    }

    public function downloadTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'Client Name');
            $sheet->setCellValue('B1', 'DB Name');
            $sheet->setCellValue('C1', 'Short Name');
            $sheet->setCellValue('D1', 'Color');
            $sheet->setCellValue('E1', 'Display Order');
            $sheet->setCellValue('F1', 'Active (Yes/No)');

            // Examples
            $sheet->setCellValue('A2', 'Transsion Ghana Ltd');
            $sheet->setCellValue('B2', 'transsion_gh');
            $sheet->setCellValue('C2', 'Transsion GH');
            $sheet->setCellValue('D2', '#3b82f6');
            $sheet->setCellValue('E2', '10');
            $sheet->setCellValue('F2', 'Yes');

            $writer = new Xlsx($spreadsheet);
            $fileName = 'clients_template.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            $writer->save($tempFile);

            return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Template download failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Template download failed.');
        }
    }

    public function bulkUpload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls|max:5120'
            ]);

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();

            array_shift($rows); // Remove header

            $existingDbNames = Client::pluck('client_dbname')->toArray();
            $errors = [];
            $validRecords = [];
            $nextOrder = Client::max('display_order') + 1;

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                $data = [
                    'client_name' => trim($row[0] ?? ''),
                    'client_dbname' => trim($row[1] ?? ''),
                    'short_name' => trim($row[2] ?? ''),
                    'color' => trim($row[3] ?? '#3b82f6'),
                    'display_order' => $row[4] !== '' ? (int)$row[4] : $nextOrder++,
                    'is_active' => in_array(strtolower(trim($row[5] ?? 'Yes')), ['yes', '1', 'true', 'active']),
                ];

                // French accent check
                foreach (['client_name', 'client_dbname', 'short_name'] as $field) {
                    if ($data[$field] && preg_match('/[ÂÊÎÔÛâêîôû]/u', $data[$field])) {
                        $errors[] = "Row $rowNumber: French accents not allowed in $field";
                        continue 2;
                    }
                }

                $rules = [
                    'client_name' => 'required|string|max:150',
                    'client_dbname' => ['required', 'string', 'max:150', Rule::notIn($existingDbNames)],
                    'short_name' => 'nullable|string|max:50',
                    'color' => 'required|regex:/^#[a-f0-9]{6}$/i',
                    'display_order' => 'integer|min:0',
                    'is_active' => 'boolean',
                ];

                $validator = Validator::make($data, $rules, [
                    'client_dbname.not_in' => "Row $rowNumber: DB Name already exists",
                    'color.regex' => "Row $rowNumber: Color must be valid hex (e.g. #3b82f6)",
                ]);

                if ($validator->fails()) {
                    $errors = array_merge($errors, array_map(fn($e) => "Row $rowNumber: $e", $validator->errors()->all()));
                    continue;
                }

                $data['slug'] = Str::slug($data['client_name']);
                $validRecords[] = $data;
                $existingDbNames[] = $data['client_dbname'];
            }

            if ($errors) {
                return redirect()->route('clients.index')
                    ->with('error', 'Upload failed: ' . implode(' | ', array_slice($errors, 0, 10)));
            }

            foreach ($validRecords as $record) {
                Client::create($record);
            }

            return redirect()->route('clients.index')
                ->with('success', "Uploaded " . count($validRecords) . " clients successfully!");

        } catch (\Exception $e) {
            Log::error('Bulk upload failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('clients.index')->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }
}
