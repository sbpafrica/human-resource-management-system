<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'manage payroll columns', 'guard_name' => 'web']);

        $hrRoles = Role::where('name', 'HR Manager')
            ->orWhere('name', 'like', '%HR%')
            ->get();

        foreach ($hrRoles as $role) {
            $role->givePermissionTo($perm);
        }

        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($perm);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $perm = Permission::where('name', 'manage payroll columns')->first();
        if ($perm) {
            $perm->delete();
        }
    }
};
