<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            // Revocar todos sus tokens de Passport
            $user->tokens()->delete();

            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta ha sido suspendida. Contacta al administrador.',
                'code'    => 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        return $next($request);
    }
}