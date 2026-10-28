<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskApiController extends BaseApiController
{
    /**
     * List tasks.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->latest('id');

        if ($request->has('is_completed')) {
            $query->where('is_completed', filter_var($request->input('is_completed'), FILTER_VALIDATE_BOOLEAN));
        }

        $tasks = $query->paginate(30);

        return $this->sendResponse([
            'items'        => $tasks->items(),
            'current_page' => $tasks->currentPage(),
            'total_pages'  => $tasks->lastPage(),
            'total_items'  => $tasks->total(),
        ], 'Tasks retrieved.');
    }

    /**
     * Create a task.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date'    => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $task = Task::create([
            'title'        => $request->title,
            'description'  => $request->description,
            'due_date'     => $request->due_date,
            'is_completed' => false,
        ]);

        return $this->sendResponse($task, 'Task created successfully.', 201);
    }

    /**
     * Toggle task completion.
     */
    public function toggle($id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return $this->sendError('Task not found.', null, 404);
        }

        $task->is_completed = !$task->is_completed;
        $task->save();

        return $this->sendResponse($task, 'Task completion toggled.');
    }

    /**
     * Delete a task.
     */
    public function destroy($id): JsonResponse
    {
        $task = Task::find($id);

        if (!$task) {
            return $this->sendError('Task not found.', null, 404);
        }

        $task->delete();

        return $this->sendResponse(null, 'Task deleted.');
    }
}
