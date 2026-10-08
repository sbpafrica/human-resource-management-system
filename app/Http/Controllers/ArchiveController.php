<?php

namespace App\Http\Controllers;

use App\Models\ArchiveRun;
use App\Services\ArchiveService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Response;

class ArchiveController extends Controller
{
    protected ArchiveService $archiveService;

    public function __construct(ArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    public function index(Request $request)
    {
        $runs = ArchiveRun::orderBy('created_at', 'desc')->paginate(10);

        return Inertia::render('Archive/Index', [
            'runs'  => $runs,
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'month' => ['required', 'string'],
            'year'  => ['required', 'string', 'size:2'],
        ]);

        $month = strtolower($request->input('month'));
        $year  = $request->input('year');

        try {
            $result = $this->archiveService->run($month, $year, true);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function run(Request $request)
    {
        $request->validate([
            'month' => ['required', 'string'],
            'year'  => ['required', 'string', 'size:2'],
        ]);

        $month = strtolower($request->input('month'));
        $year  = $request->input('year');

        try {
            $result = $this->archiveService->run($month, $year, false);
            return redirect()->back()->with('success', "Archive run completed for {$result['formattedPeriod']}. Archived: {$result['summary']['archived']}, Held: {$result['summary']['held']}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Archive run failed: " . $e->getMessage());
        }
    }

    public function downloadReport($id)
    {
        $run = ArchiveRun::findOrFail($id);
        $path = storage_path("app/archive_reports/{$run->report_filename}");

        if (!file_exists($path)) {
            return redirect()->back()->with('error', 'Report file not found.');
        }

        return response()->download($path, $run->report_filename);
    }
}
