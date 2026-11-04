<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GhanaCardSearchController extends Controller
{
    /**
     * Display a listing of staff and their Ghana Card status with search & filters.
     */
    public function index(Request $request)
    {
        $user           = $request->user();
        $search         = trim($request->input('search', ''));
        $selectedClient = $request->input('client', 'all');
        $filterType     = $request->input('filter', 'all'); // all | with_card | without_card

        // Get allowed clients for the current user
        $allowedClients = $this->getAllowedClients($user);
        $clientOptions  = $this->getClientDropdownOptions($allowedClients);

        // Base query joining newemployeedata and ssnit
        $query = DB::table('newemployeedata as e')
            ->leftJoin('ssnit as s', 'e.staffid', '=', 's.staffid')
            ->select(
                'e.staffid as staff_id',
                'e.employeename as name',
                DB::raw("COALESCE(NULLIF(e.shop, ''), NULLIF(e.company, ''), 'N/A') as company_shop"),
                'e.company',
                'e.shop',
                DB::raw("COALESCE(NULLIF(s.national, ''), NULLIF(e.ghcard, ''), '') as ghana_card"),
                DB::raw("COALESCE(NULLIF(s.ssnit1a, ''), NULLIF(e.ssnit, ''), '') as ssnit_number"),
                DB::raw("CASE WHEN (s.national IS NOT NULL AND s.national != '' AND s.national != 'NULL') OR (e.ghcard IS NOT NULL AND e.ghcard != '' AND e.ghcard != 'NULL') THEN 1 ELSE 0 END as has_card")
            );

        // Restrict by user allowed clients if non-super-admin with restrictions
        if (!$user->is_super_admin && DB::table('user_client')->where('user_id', $user->id)->exists()) {
            $allowedNames = $allowedClients->pluck('client_name')->merge($allowedClients->pluck('short_name'))->unique()->filter()->values()->all();
            if (!empty($allowedNames)) {
                $query->where(function ($q) use ($allowedNames) {
                    $q->whereIn('e.company', $allowedNames)
                      ->orWhereIn('e.shop', $allowedNames);
                    foreach ($allowedNames as $name) {
                        $q->orWhere('e.company', 'like', "%{$name}%")
                          ->orWhere('e.shop', 'like', "%{$name}%");
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Filter by Ghana Card status
        if ($filterType === 'with_card') {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNotNull('s.national')
                       ->where('s.national', '!=', '')
                       ->where('s.national', '!=', 'NULL');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('e.ghcard')
                       ->where('e.ghcard', '!=', '')
                       ->where('e.ghcard', '!=', 'NULL');
                });
            });
        } elseif ($filterType === 'without_card') {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('s.national')
                       ->orWhere('s.national', '')
                       ->orWhere('s.national', 'NULL');
                })->where(function ($sq) {
                    $sq->whereNull('e.ghcard')
                       ->orWhere('e.ghcard', '')
                       ->orWhere('e.ghcard', 'NULL');
                });
            });
        }

        // Filter by specific client from dropdown
        if ($selectedClient !== 'all') {
            $clientModel = $allowedClients->first(fn($c) => (string)$c->id === (string)$selectedClient || strtolower($c->client_name) === strtolower($selectedClient) || strtolower($c->short_name ?? '') === strtolower($selectedClient));
            if ($clientModel) {
                $names = array_unique(array_filter([$clientModel->client_name, $clientModel->short_name]));
                $query->where(function ($q) use ($names) {
                    foreach ($names as $name) {
                        $q->orWhere('e.company', 'like', "%{$name}%")
                          ->orWhere('e.shop', 'like', "%{$name}%");
                    }
                });
            }
        }

        // Global search by Staff ID, Name, Ghana Card, SSNIT, Company, or Shop
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e.staffid', 'like', "%{$search}%")
                  ->orWhere('e.employeename', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%")
                  ->orWhere('e.ghcard', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('e.company', 'like', "%{$search}%")
                  ->orWhere('e.shop', 'like', "%{$search}%");
            });
        }

        $query->orderBy('e.employeename', 'asc');

        $totalRecords = $query->count();
        $perPage      = (int) $request->input('per_page', 25);
        $records      = $query->paginate($perPage)->withQueryString();

        // Summary counts (scoped to allowed clients)
        $summaryQuery = DB::table('newemployeedata as e')
            ->leftJoin('ssnit as s', 'e.staffid', '=', 's.staffid');

        if (!$user->is_super_admin && DB::table('user_client')->where('user_id', $user->id)->exists()) {
            $allowedNames = $allowedClients->pluck('client_name')->merge($allowedClients->pluck('short_name'))->unique()->filter()->values()->all();
            if (!empty($allowedNames)) {
                $summaryQuery->where(function ($q) use ($allowedNames) {
                    $q->whereIn('e.company', $allowedNames)->orWhereIn('e.shop', $allowedNames);
                    foreach ($allowedNames as $name) {
                        $q->orWhere('e.company', 'like', "%{$name}%")->orWhere('e.shop', 'like', "%{$name}%");
                    }
                });
            }
        }

        $totalStaff = (clone $summaryQuery)->count();
        $withCard   = (clone $summaryQuery)->where(function ($q) {
            $q->where(function ($sq) {
                $sq->whereNotNull('s.national')->where('s.national', '!=', '')->where('s.national', '!=', 'NULL');
            })->orWhere(function ($sq) {
                $sq->whereNotNull('e.ghcard')->where('e.ghcard', '!=', '')->where('e.ghcard', '!=', 'NULL');
            });
        })->count();
        $withoutCard = max(0, $totalStaff - $withCard);

        return Inertia::render('HrAudit/GhanaCardSearch', [
            'records'      => $records,
            'filters'      => [
                'search' => $search,
                'client' => $selectedClient,
                'filter' => $filterType,
            ],
            'clients'      => $clientOptions,
            'totalRecords' => $totalRecords,
            'summary'      => [
                'total'        => $totalStaff,
                'with_card'    => $withCard,
                'without_card' => $withoutCard,
            ],
            'flash'        => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Update a staff member's Ghana Card and optional SSNIT number.
     * Updates both ssnit.national and newemployeedata.ghcard to ensure 100% sync.
     */
    public function update(Request $request)
    {
        $request->validate([
            'staff_id'     => ['required', 'string', 'max:50'],
            'ghana_card'   => ['nullable', 'string', 'max:100'],
            'ssnit_number' => ['nullable', 'string', 'max:50'],
        ]);

        $staffId   = trim($request->input('staff_id'));
        $rawCard   = trim((string)$request->input('ghana_card'));
        $ghanaCard = !empty($rawCard) ? strtoupper($rawCard) : null;
        $rawSsnit  = trim((string)$request->input('ssnit_number'));
        $ssnitVal  = !empty($rawSsnit) ? strtoupper($rawSsnit) : null;

        $employee = DB::table('newemployeedata')->where('staffid', $staffId)->first();

        // 1. Update newemployeedata
        if ($employee) {
            $empUpdate = ['ghcard' => $ghanaCard ?? ''];
            if ($ssnitVal !== null) {
                $empUpdate['ssnit'] = $ssnitVal;
            }
            DB::table('newemployeedata')->where('staffid', $staffId)->update($empUpdate);
        }

        // 2. Update or insert into ssnit table
        $ssnitRecord = DB::table('ssnit')->where('staffid', $staffId)->first();
        if ($ssnitRecord) {
            $ssnitUpdate = ['national' => $ghanaCard ?? ''];
            if ($ssnitVal !== null) {
                $ssnitUpdate['ssnit1a'] = $ssnitVal;
            }
            DB::table('ssnit')->where('staffid', $staffId)->update($ssnitUpdate);
        } else {
            $empName   = $employee->employeename ?? '';
            $parts     = preg_split('/\s+/', trim($empName));
            $lastname  = !empty($parts) ? array_shift($parts) : 'Employee';
            $firstname = !empty($parts) ? implode(' ', $parts) : $lastname;
            $shop      = $employee->shop ?? $employee->company ?? 'N/A';

            DB::table('ssnit')->insert([
                'staffid'   => $staffId,
                'shop'      => $shop ?: 'N/A',
                'lastname'  => $lastname,
                'firstname' => $firstname,
                'othername' => '',
                'ssnit1a'   => $ssnitVal ?? '',
                'national'  => $ghanaCard ?? '',
            ]);
        }

        Log::info("Ghana Card updated for staff {$staffId} by user ID " . auth()->id(), [
            'ghana_card' => $ghanaCard,
            'ssnit'      => $ssnitVal,
        ]);

        $successMessage = $ghanaCard
            ? "Ghana Card for {$staffId} successfully updated to {$ghanaCard}."
            : "Ghana Card for {$staffId} has been cleared.";

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => true,
                'message'    => $successMessage,
                'staff_id'   => $staffId,
                'ghana_card' => $ghanaCard ?? '',
                'ssnit'      => $ssnitVal ?? '',
            ]);
        }

        return back()->with('success', $successMessage);
    }

    /**
     * Export Ghana Card search results to Excel.
     */
    public function exportExcel(Request $request)
    {
        $user           = $request->user();
        $search         = trim($request->input('search', ''));
        $selectedClient = $request->input('client', 'all');
        $filterType     = $request->input('filter', 'all');

        $allowedClients = $this->getAllowedClients($user);

        $query = DB::table('newemployeedata as e')
            ->leftJoin('ssnit as s', 'e.staffid', '=', 's.staffid')
            ->select(
                'e.staffid as staff_id',
                'e.employeename as name',
                DB::raw("COALESCE(NULLIF(e.shop, ''), NULLIF(e.company, ''), 'N/A') as company_shop"),
                DB::raw("COALESCE(NULLIF(s.national, ''), NULLIF(e.ghcard, ''), '') as ghana_card"),
                DB::raw("COALESCE(NULLIF(s.ssnit1a, ''), NULLIF(e.ssnit, ''), '') as ssnit_number")
            );

        if (!$user->is_super_admin && DB::table('user_client')->where('user_id', $user->id)->exists()) {
            $allowedNames = $allowedClients->pluck('client_name')->merge($allowedClients->pluck('short_name'))->unique()->filter()->values()->all();
            if (!empty($allowedNames)) {
                $query->where(function ($q) use ($allowedNames) {
                    $q->whereIn('e.company', $allowedNames)->orWhereIn('e.shop', $allowedNames);
                    foreach ($allowedNames as $name) {
                        $q->orWhere('e.company', 'like', "%{$name}%")->orWhere('e.shop', 'like', "%{$name}%");
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($filterType === 'with_card') {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNotNull('s.national')->where('s.national', '!=', '')->where('s.national', '!=', 'NULL');
                })->orWhere(function ($sq) {
                    $sq->whereNotNull('e.ghcard')->where('e.ghcard', '!=', '')->where('e.ghcard', '!=', 'NULL');
                });
            });
        } elseif ($filterType === 'without_card') {
            $query->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('s.national')->orWhere('s.national', '')->orWhere('s.national', 'NULL');
                })->where(function ($sq) {
                    $sq->whereNull('e.ghcard')->orWhere('e.ghcard', '')->orWhere('e.ghcard', 'NULL');
                });
            });
        }

        if ($selectedClient !== 'all') {
            $clientModel = $allowedClients->first(fn($c) => (string)$c->id === (string)$selectedClient || strtolower($c->client_name) === strtolower($selectedClient) || strtolower($c->short_name ?? '') === strtolower($selectedClient));
            if ($clientModel) {
                $names = array_unique(array_filter([$clientModel->client_name, $clientModel->short_name]));
                $query->where(function ($q) use ($names) {
                    foreach ($names as $name) {
                        $q->orWhere('e.company', 'like', "%{$name}%")->orWhere('e.shop', 'like', "%{$name}%");
                    }
                });
            }
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('e.staffid', 'like', "%{$search}%")
                  ->orWhere('e.employeename', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%")
                  ->orWhere('e.ghcard', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('e.employeename', 'asc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ghana Card Report');

        $headers = ['No.', 'Staff ID', 'Employee Name', 'Company / Shop', 'Ghana Card No.', 'SSNIT No.', 'Status'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        $rowNum = 2;
        foreach ($data as $index => $row) {
            $hasCard = !empty($row->ghana_card) && $row->ghana_card !== 'NULL';
            $sheet->setCellValue("A{$rowNum}", $index + 1);
            $sheet->setCellValue("B{$rowNum}", $row->staff_id);
            $sheet->setCellValue("C{$rowNum}", $row->name);
            $sheet->setCellValue("D{$rowNum}", $row->company_shop);
            $sheet->setCellValue("E{$rowNum}", $row->ghana_card ?: '—');
            $sheet->setCellValue("F{$rowNum}", $row->ssnit_number ?: '—');
            $sheet->setCellValue("G{$rowNum}", $hasCard ? 'Captured' : 'Missing');
            $rowNum++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $fileName = 'Ghana_Card_Report_' . date('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getAllowedClients($user)
    {
        if ($user->is_super_admin || !DB::table('user_client')->where('user_id', $user->id)->exists()) {
            return DB::table('client')->where('is_active', 1)->orderBy('client_name')->get();
        }

        return DB::table('client')
            ->join('user_client', 'client.id', '=', 'user_client.client_id')
            ->where('user_client.user_id', $user->id)
            ->where('client.is_active', 1)
            ->orderBy('client.client_name')
            ->select('client.*')
            ->get();
    }

    private function getClientDropdownOptions($clients): array
    {
        $options = [['value' => 'all', 'label' => 'All Clients']];
        foreach ($clients as $client) {
            $options[] = [
                'value' => (string)$client->id,
                'label' => $client->client_name . ($client->short_name ? " ({$client->short_name})" : ''),
            ];
        }
        return $options;
    }
}
