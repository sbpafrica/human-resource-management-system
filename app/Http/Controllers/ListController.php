<?php

namespace App\Http\Controllers;

use App\Models\TaskList;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ListController extends Controller
{
    public function index(Request $request)
    {
        $query = TaskList::where('user_id', auth()->id());
        if ($request->has('search') && $request->search) {
            $query->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }
        $sort = $request->sort ?? 'title';
        $order = $request->order ?? 'asc';
        $query->orderBy($sort, $order);
        $lists = $query->paginate(10)->withQueryString();

        \Log::info('Lists data', ['lists' => $lists->toArray()]);

        return Inertia::render('Lists/Index', [
            'lists' => $lists,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('Store request data', ['data' => $request->all()]);
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        TaskList::create([
            'title' => $request->title,
            'description' => $request->description,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('lists.index')->with('success', 'List created successfully.');
    }

    public function update(Request $request, TaskList $list)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $list->update([
            'title' => $request->title,
            'description' => $request->description,
        ]);

        return redirect()->route('lists.index')->with('success', 'List updated successfully.');
    }

    public function destroy(TaskList $list)
    {
        $list->delete();
        return redirect()->route('lists.index')->with('success', 'List deleted successfully.');
    }
}
