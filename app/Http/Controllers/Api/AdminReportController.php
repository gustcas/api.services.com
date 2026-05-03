<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Professional;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function index()
    {
        // Por estado
        $byStatus = ServiceRequest::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Por categoría
        $byCategory = ServiceRequest::join('categories', 'service_requests.category_id', '=', 'categories.id')
            ->selectRaw('categories.name, COUNT(*) as total, SUM(CASE WHEN service_requests.status = "completed" THEN 1 ELSE 0 END) as completed, COALESCE(SUM(CASE WHEN service_requests.status = "completed" THEN service_requests.budget ELSE 0 END), 0) as revenue')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();

        // Por mes (últimos 6 meses)
        $byMonth = ServiceRequest::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw('DATE_FORMAT(created_at, "%Y-%m")')
            ->orderBy('month')
            ->get()
            ->map(function ($r) {
                return [
                    'month'     => \Carbon\Carbon::createFromFormat('Y-m', $r->month)->locale('es')->isoFormat('MMM YY'),
                    'total'     => (int) $r->total,
                    'completed' => (int) $r->completed,
                ];
            });

        // Revenue
        $revenue = [
            'total'     => (float) ServiceRequest::sum('budget'),
            'completed' => (float) ServiceRequest::where('status', 'completed')->sum('budget'),
        ];

        // Top profesionales por trabajos completados
        $topProfessionals = Professional::join('users', 'professionals.user_id', '=', 'users.id')
            ->join('service_requests', 'service_requests.professional_id', '=', 'professionals.id')
            ->where('service_requests.status', 'completed')
            ->selectRaw('users.name, professionals.id, COUNT(service_requests.id) as jobs, COALESCE(SUM(service_requests.budget), 0) as revenue')
            ->groupBy('professionals.id', 'users.name')
            ->orderByDesc('jobs')
            ->limit(5)
            ->get();

        // Stats de usuarios
        $userStats = [
            'total_clients'       => User::where('role', 'client')->count(),
            'total_professionals' => User::where('role', 'professional')->count(),
            'verified_pros'       => Professional::where('is_verified', true)->count(),
            'pending_pros'        => Professional::where('status', 'pending')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'by_status'        => $byStatus,
                'by_category'      => $byCategory,
                'by_month'         => $byMonth,
                'revenue'          => $revenue,
                'top_professionals'=> $topProfessionals,
                'user_stats'       => $userStats,
            ],
        ]);
    }
}
