<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AssistantThread;
use App\Services\Grok\HrAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssistantController extends BaseApiController
{
    protected HrAssistantService $assistantService;

    public function __construct(HrAssistantService $assistantService)
    {
        $this->assistantService = $assistantService;
    }

    /**
     * List user's active assistant chat threads.
     */
    public function indexThreads(Request $request): JsonResponse
    {
        $threads = AssistantThread::where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->paginate(20);

        return $this->sendResponse([
            'items'        => $threads->items(),
            'current_page' => $threads->currentPage(),
            'total_pages'  => $threads->lastPage(),
            'total_items'  => $threads->total(),
        ], 'Chat threads retrieved.');
    }

    /**
     * Start a new chat thread.
     */
    public function createThread(Request $request): JsonResponse
    {
        $title = $request->input('title', 'New Conversation');

        $thread = AssistantThread::create([
            'user_id' => $request->user()->id,
            'title'   => $title,
        ]);

        return $this->sendResponse($thread, 'Chat thread created.', 201);
    }

    /**
     * Get message history for a specific thread.
     */
    public function getMessages(Request $request, $threadId): JsonResponse
    {
        $thread = AssistantThread::where('user_id', $request->user()->id)->find($threadId);

        if (!$thread) {
            return $this->sendError('Conversation thread not found.', null, 404);
        }

        // Return user and assistant messages (filter out internal tool payloads for clean mobile UI)
        $messages = $thread->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id', 'asc')
            ->get(['id', 'role', 'content', 'created_at']);

        return $this->sendResponse([
            'thread_id' => $thread->id,
            'title'     => $thread->title,
            'messages'  => $messages,
        ], 'Messages retrieved.');
    }

    /**
     * Send a message to Grok AI and receive the response.
     */
    public function sendMessage(Request $request, $threadId): JsonResponse
    {
        $thread = AssistantThread::where('user_id', $request->user()->id)->find($threadId);

        if (!$thread) {
            return $this->sendError('Conversation thread not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        try {
            $reply = $this->assistantService->handleUserMessage($thread, $request->message);

            return $this->sendResponse($reply, 'Message processed successfully.');
        } catch (\Throwable $e) {
            return $this->sendError('Error communicating with Grok AI: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete a conversation thread.
     */
    public function deleteThread(Request $request, $threadId): JsonResponse
    {
        $thread = AssistantThread::where('user_id', $request->user()->id)->find($threadId);

        if (!$thread) {
            return $this->sendError('Conversation thread not found.', null, 404);
        }

        $thread->delete();

        return $this->sendResponse(null, 'Thread deleted successfully.');
    }
}
