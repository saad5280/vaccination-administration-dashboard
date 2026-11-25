<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index');
    }

    public function create(): View
    {
        return view('roles.create', ['permissions' => Permission::query()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9-]*$/', Rule::unique('roles', 'name')],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
        $permissionIds = $data['permissions'] ?? [];
        unset($data['permissions']);
        DB::transaction(function () use ($data, $permissionIds, $request): void {
            $role = Role::query()->create($data);
            $role->permissions()->sync($permissionIds);
            AuditLog::record($request, 'role.created', $role, null, [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->all(),
            ]);
        });

        return redirect()->route('roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        abort_if($role->name === 'administrator', 404);

        return view('roles.edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'administrator', 404);

        $data = $request->validate([
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
        DB::transaction(function () use ($data, $request, $role): void {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            $before = $role->permissions()->pluck('name')->all();
            $role->permissions()->sync($data['permissions'] ?? []);
            AuditLog::record($request, 'role.permissions_updated', $role, ['permissions' => $before], [
                'permissions' => $role->permissions()->pluck('name')->all(),
            ]);
        });

        return redirect()->route('roles.index')->with('status', 'Role permissions updated.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, ['administrator', 'vaccinator', 'registration', 'inventory-officer', 'manager'], true), 422, 'Built-in roles cannot be deleted.');
        abort_if($role->users()->exists(), 422, 'Remove this role from all users before deleting it.');

        DB::transaction(function () use ($request, $role): void {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            abort_if($role->users()->exists(), 422, 'Remove this role from all users before deleting it.');
            AuditLog::record($request, 'role.deleted', $role, [
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->all(),
            ]);
            $role->delete();
        });

        return redirect()->route('roles.index')->with('status', 'Role deleted.');
    }
}
