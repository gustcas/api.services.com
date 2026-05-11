<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Professional;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // ── Lista usuarios con filtros, búsqueda y paginación ──────────
    public function index(Request $request)
    {
        $query = User::with(['professional.category', 'professional.categories'])
            ->where('role', '!=', 'admin');

            
        // Búsqueda por nombre o email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por rol
        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Filtro por estado
        if ($request->filled('status') && $request->status !== 'all') {
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true);
                    break;

                case 'inactive':
                    $query->where('is_active', false);
                    break;

                case 'verified':
                    $query->whereHas('professional', function ($q) {
                        $q->where('status', 'approved');
                    });
                    break;

                case 'pending':
                    $query->where(function ($q) {
                        $q->whereDoesntHave('professional')
                          ->orWhereHas('professional', function ($q2) {
                              $q2->where('status', 'pending');
                          });
                    })->where('role', 'professional');
                    break;
            }
        }

        // Filtro por especialidad (categoría del profesional)
        if ($request->filled('specialty')) {
            $query->whereHas('professional.categories', function ($q) use ($request) {
                $q->where('categories.id', $request->specialty);
            });
        }

        // Ordenamiento
        $sortable = ['name', 'email', 'created_at', 'role', 'is_active'];
        $sort = in_array($request->sort, $sortable) ? $request->sort : 'created_at';
        $dir  = $request->dir === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $dir);

        // Paginación
        $perPage = in_array((int) $request->per_page, [10, 25, 50])
            ? (int) $request->per_page
            : 10;

        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'users'   => $users,
            'stats'   => $this->getStats(),
        ]);
    }

    // ── Ver detalle de un usuario ──────────────────────────────────
    public function show(User $user)
    {
        $user->load(['professional.category', 'professional.categories']);

        return response()->json([
            'success' => true,
            'user'    => $user,
        ]);
    }

    // ── Actualizar datos de un usuario ─────────────────────────────
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'role'  => 'sometimes|in:admin,client,professional',
        ]);

        $user->update($request->only('name', 'email', 'role'));

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente.',
            'user'    => $user->fresh(),
        ]);
    }

    // ── Habilitar / Inhabilitar usuario (toggle) ───────────────────
    public function toggleStatus(User $user)
    {
        // El admin no puede inhabilitarse a sí mismo
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes inhabilitar tu propia cuenta.',
            ], 422);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        // Si se inhabilita → revocar todos sus tokens activos de Passport
        if (!$user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success'   => true,
            'is_active' => $user->is_active,
            'message'   => $user->is_active
                ? "✅ {$user->name} ha sido habilitado."
                : "🚫 {$user->name} ha sido inhabilitado y sus sesiones fueron revocadas.",
        ]);
    }

    // ── Acciones en lote ───────────────────────────────────────────
    public function bulk(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:users,id',
            'action' => 'required|in:activate,deactivate,delete',
        ]);

        // Nunca afectar al admin autenticado
        $ids = collect($request->ids)
            ->reject(function ($id) {
                return $id === auth()->id();
            })
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede aplicar la acción a tu propia cuenta.',
            ], 422);
        }

        switch ($request->action) {

            case 'activate':
                Professional::whereIn('user_id', $ids)->update([
                    'is_verified' => 1,
                    'status' => 'approved'
                ]);
                break;

            case 'deactivate':
                Professional::whereIn('user_id', $ids)->update([
                    'is_verified' => 0,
                    'status' => 'rejected'
                ]);
                break;

            case 'delete':
                User::whereIn('id', $ids)->delete();
                break;
        }

        switch ($request->action) {
            case 'activate':
                $label = 'habilitados';
                break;

            case 'deactivate':
                $label = 'inhabilitados';
                break;

            case 'delete':
                $label = 'eliminados';
                break;

            default:
                $label = '';
        }

        return response()->json([
            'success' => true,
            'message' => "{$ids->count()} usuarios {$label} correctamente.",
            'stats'   => $this->getStats(), // devolver stats actualizados
        ]);
    }

    // ── Exportar todos los usuarios según filtros activos ─────────
    public function export(Request $request)
    {
        $query = User::with(['professional.categories'])
            ->where('role', '!=', 'admin');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true);
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                case 'verified':
                    $query->whereHas('professional', fn($q) => $q->where('status', 'approved'));
                    break;
                case 'pending':
                    $query->where(function ($q) {
                        $q->whereDoesntHave('professional')
                          ->orWhereHas('professional', fn($q2) => $q2->where('status', 'pending'));
                    })->where('role', 'professional');
                    break;
            }
        }

        if ($request->filled('specialty')) {
            $query->whereHas('professional.categories', function ($q) use ($request) {
                $q->where('categories.id', $request->specialty);
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        $rows = $users->map(fn($u) => [
            'ID'           => $u->id,
            'Nombre'       => $u->name,
            'Email'        => $u->email,
            'Rol'          => $u->role === 'client' ? 'Cliente' : 'Profesional',
            'Activo'       => $u->is_active ? 'Sí' : 'No',
            'Especialidad' => $u->professional ? $u->professional->categories->pluck('name')->join(', ') : '—',
            'Registro'     => $u->created_at ? $u->created_at->format('d/m/Y') : '—',
        ]);

        return response()->json(['success' => true, 'users' => $rows, 'total' => $rows->count()]);
    }

    // ── Filtro por especialidad: lista de categorías ───────────────
    public function specialties()
    {
        $categories = \App\Models\Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'specialties' => $categories]);
    }

    // ── Stats del dashboard ────────────────────────────────────────
    public function stats()
    {
        return response()->json([
            'success' => true,
            'stats'   => $this->getStats(),
        ]);
    }

    // ── Verificar / desverificar profesional ───────────────────────
    public function verifyProfessional(User $user)
    {
        $professional = $user->professional;
        if (!$professional) {
            return response()->json(['success' => false, 'message' => 'Sin perfil profesional'], 404);
        }

        $approved = $professional->status !== 'approved';

        // Validar requisitos antes de aprobar
        if ($approved) {
            $missing = [];

            if (empty($professional->document_number)) {
                $missing[] = 'número de documento';
            }
            if (empty($professional->phone)) {
                $missing[] = 'teléfono';
            }
            if (empty($professional->address)) {
                $missing[] = 'dirección';
            }

            $hasService = $professional->service_id
                || \DB::table('professional_services')->where('professional_id', $professional->id)->exists();

            if (!$hasService) {
                $missing[] = 'especialización / servicio asignado';
            }

            if (count($missing) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede aprobar. Faltan datos del profesional.',
                    'missing' => $missing,
                ], 422);
            }
        }

        $professional->update([
            'status'      => $approved ? 'approved' : 'pending',
            'is_verified' => $approved,
            'verified_at' => $approved ? \Carbon\Carbon::now() : null,
        ]);

        AdminLog::record(request()->user(), 'verify', 'user', $user->id,
            $approved
                ? "Verificó al profesional {$user->name}"
                : "Removió verificación de {$user->name}");

        return response()->json([
            'success'      => true,
            'message'      => $approved ? 'Profesional verificado.' : 'Verificación removida.',
            'is_verified'  => $professional->is_verified,
            'status'       => $professional->status,
            'verified_at'  => $professional->verified_at,
        ]);
    }

    // ── Método privado reutilizable para estadísticas ──────────────
    private function getStats(): array
    {
        $baseQuery = User::where('role', '!=', 'admin');

        $total         = (clone $baseQuery)->count();
        $clients       = (clone $baseQuery)->where('role', 'client')->count();
        $professionals = (clone $baseQuery)->where('role', 'professional')->count();
        $activeUsers   = (clone $baseQuery)->where('is_active', true)->count();
        $inactiveUsers = (clone $baseQuery)->where('is_active', false)->count();
        $pendingProfs  = \App\Models\Professional::where('status', 'pending')->count();
        $approvedProfs = \App\Models\Professional::where('status', 'approved')->count();

        // Estadísticas de servicios/solicitudes
        $totalRequests     = \App\Models\ServiceRequest::count();
        $pendingRequests   = \App\Models\ServiceRequest::where('status', 'pending')->count();
        $acceptedRequests  = \App\Models\ServiceRequest::where('status', 'accepted')->count();
        $completedRequests = \App\Models\ServiceRequest::where('status', 'completed')->count();
        $cancelledRequests = \App\Models\ServiceRequest::where('status', 'cancelled')->count();
        $paymentPending    = \App\Models\ServiceRequest::where('status', 'payment_pending')->count();

        // Estadísticas de pagos
        $totalCollected = \App\Models\Payment::where('status', 'approved')->sum('amount_in_cents') / 100;
        $pendingPayments = \App\Models\Payment::where('status', 'pending')->count();

        // Solicitudes de hoy
        $todayRequests = \App\Models\ServiceRequest::whereDate('created_at', today())->count();

        return [
            // Usuarios
            'totalUsers'           => $total,
            'totalClients'         => $clients,
            'totalProfessionals'   => $professionals,
            'activeUsers'          => $activeUsers,
            'inactiveUsers'        => $inactiveUsers,
            'pendingProfessionals' => $pendingProfs,
            // Solicitudes de servicio
            'totalRequests'        => $totalRequests,
            'pendingRequests'      => $pendingRequests,
            'acceptedRequests'     => $acceptedRequests,
            'completedRequests'    => $completedRequests,
            'cancelledRequests'    => $cancelledRequests,
            'paymentPendingRequests' => $paymentPending,
            'todayRequests'        => $todayRequests,
            // Pagos
            'totalCollected'       => $totalCollected,
            'pendingPayments'      => $pendingPayments,
            // Aliases por compatibilidad
            'total'         => $total,
            'clients'       => $clients,
            'professionals' => $professionals,
            'active'        => $activeUsers,
            'inactive'      => $inactiveUsers,
            'verified'      => $approvedProfs,
            'pending'       => $pendingProfs,
        ];
    }
}
