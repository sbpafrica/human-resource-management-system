<?php

namespace App\Http\Controllers;

use App\Jobs\ParseResumeJob;
use App\Models\Employeeuser;
use App\Models\ParsedResume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Exception;
use setasign\Fpdi\Tcpdf\Fpdi;

class RecruitmentController extends Controller
{
    public function skillsSearchPage(Request $request)
    {
        return Inertia::render('Recruitment/SkillsSearch');
    }

    public function searchSkills(Request $request)
    {
        $skills = $request->input('skills', '');
        $qualification = $request->input('qualification', '');
        $experience = $request->input('experience', '');
        $location = $request->input('location', '');
        $gender = $request->input('gender', '');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        $query = Employeeuser::where('role_as', 0)
            ->whereNotNull('skills')
            ->where('skills', '!=', '')
            ->select('id', 'skills', 'qualification', 'experience', 'location', 'gender', 'resume');

        if ($skills) $query->where('skills', 'like', "%{$skills}%");
        if ($qualification) $query->where('qualification', 'like', "%{$qualification}%");
        if ($experience) $query->where('experience', 'like', "%{$experience}%");
        if ($location) $query->where('location', 'like', "%{$location}%");
        if ($gender) $query->where('gender', $gender);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;
        $applicants = $query->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(function ($item) {
                $item->skills_array = $item->skills ? array_filter(explode(',', $item->skills)) : [];
                return $item;
            });

        return response()->json([
            'applicants' => $applicants,
            'total' => $total,
            'page' => (int)$page,
            'per_page' => (int)$perPage,
        ]);
    }

    public function parseResume($id)
    {
        $user = Employeeuser::findOrFail($id);

        if (!$user->resume) {
            Log::error("No resume for employeeuser_id: {$id}");
            return response()->json(['error' => 'No resume found'], 404);
        }

        ParseResumeJob::dispatch($id);

        return response()->json(['message' => 'Parsing queued']);
    }

  public function generateReformattedResume($id)
{
    try {
        $parsed = ParsedResume::where('employeeuser_id', $id)->where('parse_status', 'success')->first();

        if (!$parsed) {
            return response()->json(['message' => 'Still processing'], 202);
        }

        $parsedData = $parsed->parsed_data;
        $formattedText = $parsedData['formatted_resume'] ?? '';

        if (!$formattedText) {
            return response()->json(['error' => 'No formatted resume'], 404);
        }

        $filename = "hf_formatted_{$id}.pdf";
        $path = "blurred/{$filename}";
        $disk = Storage::disk('public');
        $disk->makeDirectory('blurred');

        $pdf = new \TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        // **SIMPLE: Write formatted text**
        $lines = explode("\n", $formattedText);
        foreach ($lines as $line) {
            if (trim($line)) {
                $pdf->Write(0, trim($line), '', 0, 'L');
                $pdf->Ln(2);
            }
        }

        $pdfContent = $pdf->Output('', 'S');
        $disk->put($path, $pdfContent);

        return response()->json(['pdf_url' => $disk->url($path) . '?ts=' . time()]);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    public function checkReformattedStatus($id)
    {
        $path = 'blurred/blurred_' . $id . '.pdf';
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return response()->json(['pdf_url' => $disk->url($path) . '?ts=' . time()]);
        }

        return response()->json(['ready' => false]);
    }
}
