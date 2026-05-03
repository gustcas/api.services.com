<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckProfessionalRole
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || $request->user()->role !== 'professional') {
            return response()->json([
                'success' => false,
                'message' => 'Acceso solo para profesionales.'
            ], 403);
        }

        return $next($request);
    }
}
