<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SubAdminController extends Controller
{
    // Permisos disponibles por módulo
    private const MODULE_PERMISSIONS = [
        'dashboard'     => ['read'],
        'users'         => ['read', 'write', 'edit', 'delete'],
        'categories'    => ['read', 'write', 'edit', 'delete'],
        'services'      => ['read', 'write', 'edit', 'delete'],
        'reports'       => ['read'],
        'payments'      => ['read'],
        'auditory'      => ['read'],
        'live_services' => ['read'],
    ];

    private const AVAILABLE_MODULES = ['dashboard', 'users', 'categories', 'services', 'reports', 'payments', 'auditory', 'live_services'];

    public function index()
    {
        $admins = User::where('role', 'admin')
            ->where('is_super_admin', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($u) => $this->format($u));

        return response()->json([
            'success'            => true,
            'admins'             => $admins,
            'modules'            => self::AVAILABLE_MODULES,
            'module_permissions' => self::MODULE_PERMISSIONS,
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
        $stored  = $u->permissions ?? [];
        $modules = [];

        foreach (self::MODULE_PERMISSIONS as $mod => $availablePerms) {
            $raw = $stored[$mod] ?? false;

            // Compatibilidad: formato antiguo era boolean
            if (is_bool($raw) || !is_array($raw)) {
                $enabled = (bool) $raw;
                $entry   = ['enabled' => $enabled];
                foreach ($availablePerms as $p) {
                    $entry[$p] = $enabled && $p === 'read'; // solo read por defecto
                }
            } else {
                $entry = ['enabled' => (bool) ($raw['enabled'] ?? false)];
                foreach ($availablePerms as $p) {
                    $entry[$p] = (bool) ($raw[$p] ?? false);
                }
            }

            $modules[$mod] = $entry;
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
        foreach (self::MODULE_PERMISSIONS as $mod => $availablePerms) {
            $raw = $modules[$mod] ?? [];

            if (is_bool($raw)) {
                $enabled = $raw;
                $entry   = ['enabled' => $enabled];
                foreach ($availablePerms as $p) {
                    $entry[$p] = $enabled && $p === 'read';
                }
            } else {
                $entry = ['enabled' => (bool) ($raw['enabled'] ?? false)];
                foreach ($availablePerms as $p) {
                    $entry[$p] = (bool) ($raw[$p] ?? false);
                }
                // Si enabled pero sin ningún permiso, forzar read
                if ($entry['enabled']) {
                    $anyPerm = array_filter($availablePerms, fn($p) => !empty($entry[$p]));
                    if (empty($anyPerm)) $entry['read'] = true;
                }
            }

            $result[$mod] = $entry;
        }
        return $result;
    }
}
