<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['permissions' => Permission::withCount('roles')->orderBy('module')->orderBy('resource')->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'max:40', Rule::enum(Module::class)],
            'resource' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z_]*$/'],
            'action' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z_]*$/',
                function (string $attribute, string $value, $fail) use ($request) {
                    $key = "{$request->input('module')}.{$request->input('resource')}.{$value}";
                    if (Permission::where('permission_string', $key)->exists()) {
                        $fail('This permission already exists.');
                    }
                }],
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $permission = Permission::create([
            ...$data, 'permission_string' => "{$data['module']}.{$data['resource']}.{$data['action']}",
            'name' => $data['name'] ?? PermissionCatalog::label($data['resource'], $data['action']),
        ]);

        return response()->json(['permission' => $permission->fresh()], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $permission->update($request->validate([
            'name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(['permission' => $permission]);
    }

    public function toggleStatus(Permission $permission): JsonResponse
    {
        $permission->update(['status' => $permission->status === 'active' ? 'inactive' : 'active']);

        return response()->json(['permission' => $permission]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        abort_if($permission->is_system, 422, 'Catalog permissions cannot be deleted. Turn them off instead.');
        abort_if($permission->roles()->exists(), 422, 'Roles still use this permission. Remove it from them first.');
        $permission->delete();

        return response()->json(['message' => 'Permission deleted.']);
    }
}
