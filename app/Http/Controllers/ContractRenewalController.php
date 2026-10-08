<?php

namespace App\Http\Controllers;

use App\Models\MelcomContractRenewal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class ContractRenewalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web',
        ];
    }

    public function index(Request $request)
    {
        $query = MelcomContractRenewal::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('position1', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'employeename', 'position1', 'startdate', 'issuedate', 'hremail', 'email'];
        $sort = $request->input('sort', 'employeename');
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'employeename';
        }
        $order = $request->input('order', 'asc');
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'asc';
        }

        $totalRecords = MelcomContractRenewal::count();

        $searchCount = $search ? (clone $query)->count() : null;

        $renewals = $query->orderBy($sort, $order)
            ->paginate(10)
            ->withQueryString()
            ->through(function ($item) {
                // The frontend expects `primary_id` for CRUD operations.
                // Since this table uses `id` (staff ID string) as its PK,
                // we expose it as both `id` and `primary_id`.
                $arr = $item->toArray();
                $arr['primary_id'] = $item->id;
                return $arr;
            });

        return inertia('ContractsRenewal/Index', [
            'renewals'     => $renewals,
            'filters'      => $request->only(['search', 'sort', 'order']),
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'flash'        => session()->only(['success', 'error']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id'           => 'required|string|max:20|unique:contractmelcomemprenew,id',
            'employeename' => 'required|string|max:100',
            'position1'    => 'required|string|max:255',
            'startdate'    => 'required|date',
            'location'     => 'nullable|string|max:255',
            'basic'        => 'nullable|string|max:50',
            'issuedate'    => 'required|date',
            'adjusted'     => 'nullable|string|max:50',
            'hremail'      => 'required|email|max:255',
            'email'        => 'nullable|email|max:100',
        ]);

        MelcomContractRenewal::create([
            'id'           => $validated['id'],
            'employeename' => $validated['employeename'],
            'position1'    => $validated['position1'],
            'startdate'    => $validated['startdate'],
            'enddate'      => $validated['location'] ?? null,
            'issuedate'    => $validated['issuedate'],
            'expireddate'  => $validated['adjusted'] ?? null,
            'employername' => $validated['basic'] ?? null,
            'hremail'      => $validated['hremail'],
            'email'        => $validated['email'] ?? null,
            'activate'     => '1',
        ]);

        return redirect()->route('contract_renewals.index')
            ->with('success', 'Contract renewal created successfully.');
    }

    public function show($primary_id)
    {
        $renewal = MelcomContractRenewal::where('id', $primary_id)->firstOrFail();

        $data = $renewal->toArray();
        $data['primary_id'] = $renewal->id;

        return response()->json($data);
    }

    public function update(Request $request, $primary_id)
    {
        $renewal = MelcomContractRenewal::where('id', $primary_id)->firstOrFail();

        $validated = $request->validate([
            'id'           => 'required|string|max:20',
            'employeename' => 'required|string|max:100',
            'position1'    => 'required|string|max:255',
            'startdate'    => 'required|date',
            'location'     => 'nullable|string|max:255',
            'basic'        => 'nullable|string|max:50',
            'issuedate'    => 'required|date',
            'adjusted'     => 'nullable|string|max:50',
            'hremail'      => 'required|email|max:255',
            'email'        => 'nullable|email|max:100',
        ]);

        $renewal->update([
            'employeename' => $validated['employeename'],
            'position1'    => $validated['position1'],
            'startdate'    => $validated['startdate'],
            'enddate'      => $validated['location'] ?? $renewal->enddate,
            'issuedate'    => $validated['issuedate'],
            'expireddate'  => $validated['adjusted'] ?? $renewal->expireddate,
            'employername' => $validated['basic'] ?? $renewal->employername,
            'hremail'      => $validated['hremail'],
            'email'        => $validated['email'] ?? null,
        ]);

        return redirect()->route('contract_renewals.index')
            ->with('success', 'Contract renewal updated successfully.');
    }

    public function destroy($primary_id)
    {
        $renewal = MelcomContractRenewal::where('id', $primary_id)->firstOrFail();
        $renewal->delete();

        return redirect()->route('contract_renewals.index')
            ->with('success', 'Contract renewal deleted successfully.');
    }

    public function download($primary_id, Request $request)
    {
        $renewal = MelcomContractRenewal::where('id', $primary_id)->firstOrFail();

        $firstname = ucfirst(strtolower(explode(' ', trim($renewal->employeename))[0]));

        $data = [
            'employeename' => $renewal->employeename,
            'firstname'    => $firstname,
            'position'     => $renewal->position1,
            'startdate'    => $renewal->startdate ? Carbon::parse($renewal->startdate)->format('F d, Y') : 'N/A',
            'enddate'      => $renewal->enddate ? Carbon::parse($renewal->enddate)->format('F d, Y') : 'N/A',
            'issuedate'    => $renewal->issuedate ? Carbon::parse($renewal->issuedate)->format('F d, Y') : 'N/A',
            'expireddate'  => $renewal->expireddate ? Carbon::parse($renewal->expireddate)->format('F d, Y') : 'N/A',
            'location'     => $renewal->enddate ?? 'N/A',
            'adjusted'     => $renewal->expireddate ?? 'N/A',
            'basic'        => $renewal->employername ?? 'N/A',
            'signature'    => $request->input('no_signature') ? null : $renewal->signature,
        ];

        $pdf = Pdf::loadView('pdf.contract_renewal', $data)
            ->setPaper('a4', 'portrait');

        $filename = "contract_renewal_{$primary_id}.pdf";

        if ($request->input('no_signature')) {
            return $pdf->stream($filename);
        }

        // Bundle CV with renewal document if CV exists for employee
        $cvPath = $this->findEmployeeCv($renewal->id, $renewal->employeename);

        if ($cvPath && file_exists($cvPath) && !$request->has('pdf_only')) {
            $zipPath = storage_path('app/temp/renewal_package_melcom_' . $renewal->id . '.zip');
            $zipDir = dirname($zipPath);
            if (!is_dir($zipDir)) {
                mkdir($zipDir, 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $zip->addFromString($filename, $pdf->output());
                $cvExt = pathinfo($cvPath, PATHINFO_EXTENSION) ?: 'pdf';
                $cvFilename = "cv_{$renewal->employeename}_{$renewal->id}.{$cvExt}";
                $zip->addFile($cvPath, $cvFilename);
                $zip->close();

                return response()->download($zipPath, "renewal_package_{$renewal->employeename}_{$renewal->id}.zip")->deleteFileAfterSend(true);
            }
        }

        return $pdf->download($filename);
    }

    private function findEmployeeCv($staffId, $employeeName)
    {
        $resumeFilename = null;

        // 1. Check recruit table
        $recruit = DB::table('recruit')
            ->where('id', $staffId)
            ->orWhere('fullname', 'like', "%{$employeeName}%")
            ->first();
        if ($recruit && !empty($recruit->resume)) {
            $resumeFilename = $recruit->resume;
        }

        // 2. Check sbpltd table
        if (!$resumeFilename) {
            $sbpltd = DB::table('sbpltd')
                ->where('employnum', $staffId)
                ->orWhere('fullname', 'like', "%{$employeeName}%")
                ->first();
            if ($sbpltd && !empty($sbpltd->resume)) {
                $resumeFilename = $sbpltd->resume;
            }
        }

        $candidatePaths = [];

        if ($resumeFilename) {
            $cleanName = basename($resumeFilename);
            $candidatePaths = array_merge($candidatePaths, [
                public_path('resumes/' . $cleanName),
                public_path('resumes/' . $resumeFilename),
                public_path('storage/resumes/' . $cleanName),
                storage_path('app/public/resumes/' . $cleanName),
                storage_path('app/resumes/' . $cleanName),
                storage_path('app/public/' . $cleanName),
                public_path('uploads/cv/' . $cleanName),
                storage_path('app/public/cv/' . $cleanName),
                '/home/u1710176/hrs.sbpafrica.group/public/resumes/' . $cleanName,
                '/home/u1710176/hrs.sbpafrica.group/storage/app/public/resumes/' . $cleanName,
            ]);
        }

        $candidatePaths = array_merge($candidatePaths, [
            public_path('resumes/' . $staffId . '.pdf'),
            public_path('resumes/' . $staffId . '.docx'),
            storage_path('app/public/resumes/' . $staffId . '.pdf'),
            storage_path('app/public/resumes/' . $staffId . '.docx'),
            storage_path('app/public/cv/' . $staffId . '.pdf'),
        ]);

        foreach ($candidatePaths as $path) {
            if (!empty($path) && file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['id', 'employeename', 'position1', 'startdate', 'enddate', 'issuedate', 'expireddate', 'hremail', 'email'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(chr(65 + $i) . '1', $header);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $tempFile = tempnam(sys_get_temp_dir(), 'renewal_template_');
        $writer->save($tempFile);

        return response()->download($tempFile, 'contract_renewals_template.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $headers = array_shift($rows);
            $headers = array_map('strtolower', array_map('trim', $headers));

            $created = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $data = array_combine($headers, $row);

                if (empty($data['id']) || empty($data['employeename'])) {
                    $skipped++;
                    continue;
                }

                $exists = MelcomContractRenewal::where('id', $data['id'])->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                MelcomContractRenewal::create([
                    'id'           => $data['id'],
                    'employeename' => $data['employeename'] ?? '',
                    'position1'    => $data['position1'] ?? '',
                    'startdate'    => $this->parseDate($data['startdate'] ?? null),
                    'enddate'      => $this->parseDate($data['enddate'] ?? null),
                    'issuedate'    => $this->parseDate($data['issuedate'] ?? null),
                    'expireddate'  => $this->parseDate($data['expireddate'] ?? null),
                    'hremail'      => $data['hremail'] ?? '',
                    'email'        => $data['email'] ?? null,
                    'activate'     => '1',
                ]);

                $created++;
            }

            return redirect()->route('contract_renewals.index')
                ->with('success', "Bulk upload complete: {$created} created, {$skipped} skipped.");

        } catch (\Exception $e) {
            Log::error('Bulk upload failed: ' . $e->getMessage());

            return redirect()->route('contract_renewals.index')
                ->with('error', 'Bulk upload failed: ' . $e->getMessage());
        }
    }

    private function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((int) $value)->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}