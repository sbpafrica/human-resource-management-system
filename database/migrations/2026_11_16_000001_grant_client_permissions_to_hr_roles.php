<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure permissions exist
        $permissions = [
            'view clients',
            'manage payroll columns',
            'view leave',
            'view dashboard',
            'view payroll',
            'view ssnit',
            'view petra',
            'view contracts',
            'view payslips',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 1. HR Manager Role
        $hrManager = Role::firstOrCreate(['name' => 'HR Manager', 'guard_name' => 'web']);
        $hrManager->givePermissionTo([
            'view clients',
            'manage payroll columns',
            'view leave',
            'view dashboard',
            'view payroll',
            'view ssnit',
            'view petra',
            'view payslips',
            'view contracts',
        ]);

        // 2. HR Officer Role
        $hrOfficer = Role::firstOrCreate(['name' => 'HR Officer', 'guard_name' => 'web']);
        $hrOfficer->givePermissionTo([
            'view clients',
            'manage payroll columns',
            'view leave',
            'view dashboard',
            'view payroll',
            'view ssnit',
            'view petra',
            'view contracts',
            'view payslips',
        ]);

        // 3. Payroll Officer Role (often holds HRBP duties in current system)
        $payrollOfficer = Role::firstOrCreate(['name' => 'Payroll Officer', 'guard_name' => 'web']);
        $payrollOfficer->givePermissionTo([
            'view clients',
            'manage payroll columns',
            'view leave',
        ]);

        // Reset cache again
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
    }
};
