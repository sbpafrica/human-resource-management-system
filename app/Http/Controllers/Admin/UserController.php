<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Client;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles', 'clients')->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    // NEW: Show create form
    public function create()
    {
        $this->ensureDefaultRolesExist();

        return Inertia::render('Admin/Users/Create', [
            'allRoles'   => Role::all(),
            'allClients' => Client::orderBy('display_order')->get(['id', 'client_name', 'short_name', 'slug']),
        ]);
    }

    // NEW: Store new user
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'roles'    => 'array',
            'clients'  => 'array',
            'clients.*'=> 'exists:client,id',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign roles
        if ($request->roles) {
            $user->syncRoles($request->roles);
        }

        // Assign clients
        if ($request->clients) {
            $user->clients()->sync($request->clients);
        }

        return redirect()->route('admin.users.index')->with('success', 'User created successfully');
    }

    public function edit(User $user)
    {
        $this->ensureDefaultRolesExist();

        $user->load('roles', 'clients');

        return Inertia::render('Admin/Users/Edit', [
            'user'       => $user,
            'allRoles'   => Role::all(),
            'allClients' => Client::orderBy('display_order')->get(['id', 'client_name', 'short_name', 'slug']),
        ]);
    }

    private function ensureDefaultRolesExist(): void
    {
        try {
            $defaultRoles = [
                'Super Admin',
                'Payroll Officer',
                'Receipt Manager',
                'Receipt Officer',
            ];

            foreach ($defaultRoles as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }
        } catch (\Throwable $e) {
            // Ignore if DB is temporary unreachable
        }
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles'    => 'array',
            'clients'  => 'array',
            'clients.*'=> 'exists:client,id',
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
            'password' => $request->filled('password') ? Hash::make($request->password) : $user->password,
        ]);

        $user->syncRoles($request->roles ?? []);
        $user->clients()->sync($request->clients ?? []);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully');
    }
}
