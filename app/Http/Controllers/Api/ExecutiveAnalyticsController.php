<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\Analytics\ExecutiveAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExecutiveAnalyticsController extends Controller
{
    public function __construct(
        protected ExecutiveAnalyticsService $analyticsService
    ) {}

    /**
     * Executes natural language query for leadership.
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $question = $request->input('question');
        $user = $request->user();

        $result = $this->analyticsService->ask($question, [
            'user_id' => $user->id ?? null,
            'is_super_admin' => $user->is_super_admin ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Returns curated prompt suggestions for executive quick-insights.
     */
    public function suggestions(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'suggestions' => [
                "What was our total Petra Tier 2 liability across all OFI sites for last quarter?",
                "Compare overtime spend between Twellium Accra and Twellium Kumasi.",
                "Which client had the highest gross payroll last month?",
                "Show active casual headcount and average daily rates across all factory clients.",
                "List all clients where statutory Tier 1 SSNIT contributions were processed this month.",
            ],
        ]);
    }
}
