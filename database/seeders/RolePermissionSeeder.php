<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'dashboard.view' => 'View dashboard',
            'users.manage' => 'Manage users',
            'roles.manage' => 'Manage roles and permissions',
            'children.view' => 'View children',
            'children.manage' => 'Register and update children',
            'vaccinations.view' => 'View vaccination history',
            'vaccinations.record' => 'Record vaccination doses',
            'vaccinations.amend' => 'Correct vaccination records',
            'vaccinations.clinical_authorize' => 'Authorize clinical dose exceptions and date overrides',
            'due-register.view' => 'View due and overdue register',
            'appointments.manage' => 'Manage appointments',
            'reminders.manage' => 'Manage reminders and follow-up',
            'inventory.manage' => 'Manage vaccine inventory',
            'reports.view' => 'View reports and exports',
            'settings.manage' => 'Manage system settings',
            'audit.view' => 'View audit log',
            'catalogue.view' => 'View vaccine catalogue',
            'catalogue.manage' => 'Manage vaccine catalogue',
            'schedules.view' => 'View immunization schedules',
            'schedules.manage' => 'Manage schedule drafts',
            'schedules.approve' => 'Approve immunization schedules',
            'schedules.assign' => 'Assign approved schedules to children',
        ];

        foreach ($permissions as $name => $label) {
            Permission::query()->updateOrCreate(['name' => $name], ['label' => $label]);
        }

        $allPermissions = Permission::query()->get();
        $rolePermissions = [
            'administrator' => $allPermissions->modelKeys(),
            'vaccinator' => $allPermissions->whereIn('name', ['dashboard.view', 'children.view', 'vaccinations.view', 'vaccinations.record', 'vaccinations.clinical_authorize', 'due-register.view', 'appointments.manage', 'catalogue.view', 'schedules.view'])->modelKeys(),
            'registration' => $allPermissions->whereIn('name', ['dashboard.view', 'children.view', 'children.manage', 'appointments.manage', 'reminders.manage'])->modelKeys(),
            'inventory-officer' => $allPermissions->whereIn('name', ['dashboard.view', 'inventory.manage'])->modelKeys(),
            'manager' => $allPermissions->whereIn('name', ['dashboard.view', 'reports.view'])->modelKeys(),
        ];

        foreach ($rolePermissions as $name => $permissionIds) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name],
                ['label' => str($name)->replace('-', ' ')->title()->toString()],
            );
            $role->permissions()->sync($permissionIds);
        }
    }
}
