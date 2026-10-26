<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\Copilot\EmployeeCopilotService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeCopilotController extends Controller
{
    public function __construct(
        protected EmployeeCopilotService $copilotService
    ) {}

    /**
     * Authenticates employee via Staff ID + 4-digit PIN or Ghana Card.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => ['required', 'string'],
            'pin' => ['required', 'string'],
        ]);

        $employee = $this->copilotService->authenticate(
            $request->input('staff_id'),
            $request->input('pin')
        );

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Staff ID or PIN. Please verify your details or contact HR.',
            ], 401);
        }

        // Store authenticated staff_id in session
        session(['copilot_staff_id' => $employee['staff_id']]);

        $context = $this->copilotService->getContext($employee['staff_id']);

        return response()->json([
            'success' => true,
            'employee' => $employee,
            'context' => $context,
        ]);
    }

    /**
     * Handles chatbot conversational interaction.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'staff_id' => ['nullable', 'string'],
            'history' => ['nullable', 'array'],
        ]);

        $staffId = $request->input('staff_id') ?: session('copilot_staff_id');

        if (empty($staffId)) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please log in with your Staff ID and PIN.',
            ], 401);
        }

        $result = $this->copilotService->handleMessage(
            $staffId,
            $request->input('message'),
            $request->input('history', [])
        );

        return response()->json([
            'success' => true,
            'reply' => $result['reply'],
            'action' => $result['action'],
            'context_summary' => $result['context_summary'],
        ]);
    }

    /**
     * Retrieves quick dashboard stats for authenticated employee.
     */
    public function quickStats(Request $request): JsonResponse
    {
        $staffId = $request->input('staff_id') ?: session('copilot_staff_id');

        if (empty($staffId)) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $context = $this->copilotService->getContext($staffId);

        return response()->json([
            'success' => true,
            'context' => $context,
        ]);
    }
}
