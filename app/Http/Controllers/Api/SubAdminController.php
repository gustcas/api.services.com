<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SubAdminController extends Controller
{
    private const AVAILABLE_MODULES = ['dashboard', 'users', 'categories', 'services', 'reports', 'auditory'];

    public function index()
    {
        $admins = User::where('role', 'admin')
            ->where('is_super_admin', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($u) => $this->format($u));

        return response()->json([
            'success' => true,
            'admins'  => $admins,
            'modules' => self::AVAILABLE_MODULES,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'modules'  => 'nullable|array',
        ]);

        $user = User::create([
            'name'           => $request->name,
            'email'          => $request->email,
            'password'       => Hash::make($request->password),
            'role'           => 'admin',
            'is_super_admin' => false,
            'is_active'      => true,
            'permissions'    => $this->normalizeModules($request->modules ?? []),
        ]);

        AdminLog::record($request->user(), 'create', 'sub-admin', $user->id,
            "Creó el sub-administrador {$user->name} ({$user->email})");

        return response()->json([
            'success' => true,
            'admin'   => $this->format($user),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        if ($user->is_super_admin) {
            return response()->json(['success' => false, 'message' => 'No puedes editar al super administrador.'], 403);
        }

        $request->validate([
            'name'      => 'sometimes|string|max:255',
            'modules'   => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = [];
        if ($request->has('name'))      $data['name']        = $request->name;
        if ($request->has('modules'))   $data['permissions'] = $this->normalizeModules($request->modules);
        if ($request->has('is_active')) $data['is_active']   = $request->is_active;

        $user->update($data);

        if (isset($data['is_active']) && !$data['is_active']) {
            $user->tokens()->delete();
        }

        AdminLog::record($request->user(), 'update', 'sub-admin', $user->id,
            "Actualizó el sub-administrador {$user->name}",
            array_keys($data));

        return response()->json([
            'success' => true,
            'admin'   => $this->format($user->fresh()),
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->is_super_admin) {
            return response()->json(['success' => false, 'message' => 'No puedes eliminar al super administrador.'], 403);
        }

        $name = $user->name;
        $user->tokens()->delete();
        $user->delete();

        AdminLog::record(request()->user(), 'delete', 'sub-admin', null,
            "Eliminó el sub-administrador {$name}");

        return response()->json(['success' => true, 'message' => 'Administrador eliminado.']);
    }

    private function format(User $u): array
    {
        $permissions = $u->permissions ?? [];
        $modules = [];
        foreach (self::AVAILABLE_MODULES as $mod) {
            $modules[$mod] = (bool) ($permissions[$mod] ?? false);
        }

        return [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'is_active'  => (bool) $u->is_active,
            'modules'    => $modules,
            'created_at' => $u->created_at ? $u->created_at->format('d/m/Y') : null,
        ];
    }

    private function normalizeModules(array $modules): array
    {
        $result = [];
        foreach (self::AVAILABLE_MODULES as $mod) {
            $result[$mod] = (bool) ($modules[$mod] ?? false);
        }
        return $result;
    }
}
