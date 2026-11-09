<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Client;
use App\Mail\EmployeeDocumentMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use ZipArchive;

class EmployeeDocumentController extends Controller
{
    /**
     * Display a listing of dispatched employee documents.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Determine accessible clients
        $clientsQuery = ($user->is_super_admin || !$user->clients()->exists())
            ? Client::where('is_active', 1)
            : $user->clients()->where('is_active', 1);

        $accessibleClients = $clientsQuery->orderBy('client_name')->get(['id', 'client_name', 'short_name']);
        $clientNames = $accessibleClients->pluck('client_name')->toArray();
        $shortNames  = $accessibleClients->pluck('short_name')->toArray();
        $allowedClientNames = array_values(array_unique(array_filter(array_merge($clientNames, $shortNames))));

        $query = EmployeeDocument::query()->with('sender:id,name,email');

        // Apply client scoping for non-superadmins
        if (!$user->is_super_admin && $user->clients()->exists()) {
            $query->where(function ($q) use ($allowedClientNames) {
                $q->whereIn('client_name', $allowedClientNames)
                  ->orWhereNull('client_name');
            });
        }

        // Search filter
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('staff_id', 'like', "%{$search}%")
                  ->orWhere('employee_name', 'like', "%{$search}%")
                  ->orWhere('recipient_email', 'like', "%{$search}%")
                  ->orWhere('document_title', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        // Client filter
        if ($client = $request->input('client')) {
            $query->where('client_name', $client);
        }

        // Document Type filter
        if ($type = $request->input('type')) {
            $query->where('document_type', $type);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Order by newest first
        $query->orderBy('created_at', 'desc');

        $documents = $query->paginate(15)->withQueryString();

        // Calculate metrics
        $metricsBase = EmployeeDocument::query();
        if (!$user->is_super_admin && $user->clients()->exists()) {
            $metricsBase->where(function ($q) use ($allowedClientNames) {
                $q->whereIn('client_name', $allowedClientNames)
                  ->orWhereNull('client_name');
            });
        }

        $metrics = [
            'total_sent' => (clone $metricsBase)->where('status', 'sent')->count(),
            'sent_this_month' => (clone $metricsBase)->where('status', 'sent')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'unique_employees' => (clone $metricsBase)->distinct('staff_id')->count('staff_id'),
            'failed' => (clone $metricsBase)->where('status', 'failed')->count(),
        ];

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'filters'   => $request->only(['search', 'client', 'type', 'status']),
            'clients'   => $accessibleClients,
            'metrics'   => $metrics,
            'currentUser' => [
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Autocomplete employee search for single upload modal.
     */
    public function searchEmployees(Request $request)
    {
        $query = trim($request->input('q', ''));
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = Employee::query()
            ->where(function ($q) use ($query) {
                $q->where('staffid', 'like', "%{$query}%")
                  ->orWhere('employeename', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->select(['staffid as staff_id', 'employeename as employee_name', 'company as client_name', 'email'])
            ->limit(15)
            ->get();

        return response()->json($results);
    }

    /**
     * Store and immediately dispatch a single employee document.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id'        => ['required', 'string', 'max:50'],
            'employee_name'   => ['nullable', 'string', 'max:200'],
            'client_name'     => ['nullable', 'string', 'max:150'],
            'document_type'   => ['required', 'string', 'max:100'],
            'document_title'  => ['required', 'string', 'max:255'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'file'            => ['required', 'file', 'max:25600', 'mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx,zip'],
            'cc_hr'           => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getMimeType();
        $fileSize = $uploadedFile->getSize();

        // Store file safely under storage/app/documents/YYYY/MM
        $subDir = 'documents/' . date('Y/m');
        $storedPath = $uploadedFile->store($subDir);

        $document = EmployeeDocument::create([
            'staff_id'        => $validated['staff_id'],
            'employee_name'   => $validated['employee_name'],
            'client_name'     => $validated['client_name'],
            'document_type'   => $validated['document_type'],
            'document_title'  => $validated['document_title'],
            'file_name'       => $originalName,
            'file_path'       => $storedPath,
            'file_size'       => $fileSize,
            'file_mime'       => $mimeType,
            'recipient_email' => $validated['recipient_email'],
            'sender_user_id'  => $user->id,
            'sender_email'    => $user->email,
            'status'          => 'pending',
            'notes'           => $validated['notes'] ?? null,
        ]);

        $absolutePath = Storage::path($storedPath);

        // Attempt sending email
        try {
            $mail = Mail::to($document->recipient_email);

            if (($validated['cc_hr'] ?? true) && !empty($user->email)) {
                $mail->cc($user->email);
            }

            $mail->send(new EmployeeDocumentMail($document, $absolutePath));

            $document->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            return redirect()->back()->with('success', "Document successfully sent to {$document->recipient_email}!");
        } catch (\Throwable $e) {
            Log::error('Failed to send employee document email', [
                'document_id' => $document->id,
                'staff_id'    => $document->staff_id,
                'error'       => $e->getMessage(),
            ]);

            $document->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', "Document uploaded, but failed to send email: {$e->getMessage()}");
        }
    }

    /**
     * Bulk upload and dispatch documents.
     * Matches documents to employees by Staff ID found in the file name.
     */
    public function bulkUpload(Request $request)
    {
        $validated = $request->validate([
            'document_type'  => ['required', 'string', 'max:100'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'client_name'    => ['nullable', 'string', 'max:150'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'cc_hr'          => ['nullable', 'boolean'],
            'files'          => ['nullable', 'array'],
            'files.*'        => ['file', 'max:25600'],
            'archive'        => ['nullable', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $user = $request->user();
        $filesToProcess = [];
        $tempExtractDir = null;

        // Check if ZIP archive was uploaded
        if ($request->hasFile('archive')) {
            $zipFile = $request->file('archive');
            $zip = new ZipArchive();
            $tempExtractDir = storage_path('app/temp_bulk_' . uniqid());

            if ($zip->open($zipFile->getRealPath()) === true) {
                $zip->extractTo($tempExtractDir);
                $zip->close();

                // Recursively find all files
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempExtractDir));
                foreach ($iterator as $item) {
                    if ($item->isFile() && !str_starts_with($item->getFilename(), '.')) {
                        $filesToProcess[] = [
                            'path'     => $item->getPathname(),
                            'filename' => $item->getFilename(),
                            'size'     => $item->getSize(),
                            'mime'     => mime_content_type($item->getPathname()) ?: 'application/octet-stream',
                            'is_temp'  => true,
                        ];
                    }
                }
            } else {
                return redirect()->back()->with('error', 'Unable to extract the uploaded ZIP archive.');
            }
        } elseif ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $filesToProcess[] = [
                    'uploaded_file' => $file,
                    'filename'      => $file->getClientOriginalName(),
                    'size'          => $file->getSize(),
                    'mime'          => $file->getMimeType(),
                    'is_temp'       => false,
                ];
            }
        } else {
            return redirect()->back()->with('error', 'Please provide either multiple files or a ZIP archive.');
        }

        if (empty($filesToProcess)) {
            if ($tempExtractDir && is_dir($tempExtractDir)) {
                $this->deleteDirectory($tempExtractDir);
            }
            return redirect()->back()->with('error', 'No valid files found to process.');
        }

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($filesToProcess as $item) {
            $filename = $item['filename'];
            $baseName = pathinfo($filename, PATHINFO_FILENAME);

            // Extract potential staff ID: look for first alphanumeric word (e.g. SBP1001, SBP-1002, 10234)
            $matchedStaffId = null;
            if (preg_match('/^([A-Za-z0-9_-]+)/', $baseName, $m)) {
                $matchedStaffId = trim(str_replace(['_', '-'], '', $m[1]));
            }

            // Look up employee in database
            $employee = null;
            if ($matchedStaffId) {
                $employee = Employee::where('staffid', $matchedStaffId)
                    ->orWhere('staffid', 'like', "%{$matchedStaffId}%")
                    ->first();
            }

            // Fallback: check exact baseName as staffid
            if (!$employee) {
                $employee = Employee::where('staffid', $baseName)->first();
            }

            if (!$employee) {
                $skippedCount++;
                $errors[] = "{$filename}: Employee staff ID could not be identified from filename.";
                continue;
            }

            if (empty($employee->email) || !filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
                $skippedCount++;
                $errors[] = "{$filename}: Employee {$employee->staffid} ({$employee->employeename}) has no valid email address on file.";
                continue;
            }

            // Save file to permanent storage
            $subDir = 'documents/' . date('Y/m');
            $destName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            $storedPath = $subDir . '/' . $destName;

            if ($item['is_temp']) {
                Storage::put($storedPath, file_get_contents($item['path']));
            } else {
                $item['uploaded_file']->storeAs($subDir, $destName);
            }

            $docTitle = !empty($validated['document_title']) 
                ? $validated['document_title'] 
                : ucwords(str_replace(['_', '-'], ' ', $baseName));

            $document = EmployeeDocument::create([
                'staff_id'        => $employee->staffid,
                'employee_name'   => $employee->employeename,
                'client_name'     => $validated['client_name'] ?: $employee->company,
                'document_type'   => $validated['document_type'],
                'document_title'  => $docTitle,
                'file_name'       => $filename,
                'file_path'       => $storedPath,
                'file_size'       => $item['size'],
                'file_mime'       => $item['mime'],
                'recipient_email' => $employee->email,
                'sender_user_id'  => $user->id,
                'sender_email'    => $user->email,
                'status'          => 'pending',
                'notes'           => $validated['notes'] ?? null,
            ]);

            $absolutePath = Storage::path($storedPath);

            try {
                $mail = Mail::to($document->recipient_email);
                if (($validated['cc_hr'] ?? true) && !empty($user->email)) {
                    $mail->cc($user->email);
                }
                $mail->send(new EmployeeDocumentMail($document, $absolutePath));

                $document->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
                $sentCount++;
            } catch (\Throwable $e) {
                Log::error('Bulk upload email failed', [
                    'document_id' => $document->id,
                    'staff_id'    => $document->staff_id,
                    'error'       => $e->getMessage(),
                ]);
                $document->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $failedCount++;
                $errors[] = "{$filename}: Failed to send email to {$employee->email} ({$e->getMessage()})";
            }
        }

        // Clean up temporary extracted directory if created
        if ($tempExtractDir && is_dir($tempExtractDir)) {
            $this->deleteDirectory($tempExtractDir);
        }

        $summaryMsg = "Bulk processing complete: {$sentCount} document(s) sent successfully.";
        if ($skippedCount > 0) {
            $summaryMsg .= " {$skippedCount} file(s) skipped.";
        }
        if ($failedCount > 0) {
            $summaryMsg .= " {$failedCount} file(s) failed delivery.";
        }

        if ($failedCount > 0 || $skippedCount > 0) {
            return redirect()->back()
                ->with('success', $summaryMsg)
                ->with('warning', implode(' | ', array_slice($errors, 0, 5)));
        }

        return redirect()->back()->with('success', $summaryMsg);
    }

    /**
     * Download stored copy of a document.
     */
    public function download(Request $request, $id)
    {
        $document = EmployeeDocument::findOrFail($id);

        if (!Storage::exists($document->file_path)) {
            return redirect()->back()->with('error', 'The requested file could not be found in storage.');
        }

        return Storage::download($document->file_path, $document->file_name);
    }

    /**
     * Resend an already uploaded document to the recipient email.
     */
    public function resend(Request $request, $id)
    {
        $document = EmployeeDocument::findOrFail($id);
        $user = $request->user();

        if (!Storage::exists($document->file_path)) {
            return redirect()->back()->with('error', 'The document file is missing from storage and cannot be resent.');
        }

        $absolutePath = Storage::path($document->file_path);

        try {
            $mail = Mail::to($document->recipient_email);
            if (!empty($user->email)) {
                $mail->cc($user->email);
            }
            $mail->send(new EmployeeDocumentMail($document, $absolutePath));

            $document->update([
                'status'        => 'sent',
                'sent_at'       => now(),
                'error_message' => null,
            ]);

            return redirect()->back()->with('success', "Document resent successfully to {$document->recipient_email}!");
        } catch (\Throwable $e) {
            Log::error('Resend employee document failed', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);

            $document->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', "Failed to resend email: {$e->getMessage()}");
        }
    }

    /**
     * Helper to recursively delete temporary directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
