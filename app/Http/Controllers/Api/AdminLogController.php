<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminLog::orderBy('created_at', 'desc');

        if ($request->filled('entity')) {
            $query->where('entity', $request->entity);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('admin_name', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $logs = $query->paginate((int) $request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'logs'    => $logs->items(),
            'total'   => $logs->total(),
            'pages'   => $logs->lastPage(),
            'current' => $logs->currentPage(),
        ]);
    }
}
