<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // List of permissions – using firstOrCreate = safe to run 1000 times
        $permissions = [
            'view dashboard',
            'view clients',
            'view bankcodes',
            'view performance',
            'view assets',
            'view gra',
            'manage users',
            'view payroll',
            'view ssnit',
            'view petra',
            'view payslips',
            'view contracts',
            'view leave',
            'view receipts',
            'manage receipts',
            'view attendance',
            'view database',
            'view hr audit',
            'view transsion bfa',
            'view bunge',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Roles
        $superAdmin     = Role::firstOrCreate(['name' => 'Super Admin']);
        $officer        = Role::firstOrCreate(['name' => 'Payroll Officer']);
        $receiptManager = Role::firstOrCreate(['name' => 'Receipt Manager']);
        $receiptOfficer = Role::firstOrCreate(['name' => 'Receipt Officer']);

        // Sync all permissions to Super Admin
        $superAdmin->syncPermissions(Permission::all());

        // Give limited permissions to Payroll Officer
        $officer->syncPermissions([
            'view dashboard',
            'view clients',
            'view payroll',
            'view ssnit',
            'view petra',
            'view payslips',
            'view leave',
        ]);

        // Sync permissions for Receipt Manager & Officer
        $receiptManager->syncPermissions([
            'view dashboard',
            'view receipts',
            'manage receipts',
        ]);

        $receiptOfficer->syncPermissions([
            'view dashboard',
            'view receipts',
            'manage receipts',
        ]);

        // Make user ID 1 (or your admin) Super Admin
        $admin = \App\Models\User::find(1); // Change to your admin user ID
        if ($admin) {
            $admin->assignRole('Super Admin');
            $admin->is_super_admin = true;
            $admin->save();
        }
    }
}
