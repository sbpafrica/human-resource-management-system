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

        // Ensure permissions exist
        $viewReceipts   = Permission::firstOrCreate(['name' => 'view receipts', 'guard_name' => 'web']);
        $manageReceipts = Permission::firstOrCreate(['name' => 'manage receipts', 'guard_name' => 'web']);
        $viewDashboard  = Permission::firstOrCreate(['name' => 'view dashboard', 'guard_name' => 'web']);

        // Create Receipt Manager role
        $receiptManager = Role::firstOrCreate(['name' => 'Receipt Manager', 'guard_name' => 'web']);
        $receiptManager->syncPermissions([$viewDashboard, $viewReceipts, $manageReceipts]);

        // Create Receipt Officer role
        $receiptOfficer = Role::firstOrCreate(['name' => 'Receipt Officer', 'guard_name' => 'web']);
        $receiptOfficer->syncPermissions([$viewDashboard, $viewReceipts, $manageReceipts]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::whereIn('name', ['Receipt Manager', 'Receipt Officer'])->delete();
    }
};
