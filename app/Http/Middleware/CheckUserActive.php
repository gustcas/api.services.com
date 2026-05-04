<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            if (!$user->is_active) {
                $user->tokens()->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Tu cuenta ha sido suspendida. Contacta al administrador.',
                    'code'    => 'ACCOUNT_SUSPENDED',
                ], 403);
            }

            // Actualiza last_seen_at en cada petición autenticada
            \DB::table('users')
                ->where('id', $user->id)
                ->update(['last_seen_at' => \Carbon\Carbon::now('UTC')]);
        }

        return $next($request);
    }
}