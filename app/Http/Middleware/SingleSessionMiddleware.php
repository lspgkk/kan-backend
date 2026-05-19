<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SingleSessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tokenVersion = auth()->payload()->get('token_version');

        // Token without token_version claim — allow only if DB version is still default (pre-migration)
        if ($tokenVersion === null) {
            if ((int) $user->token_version === 0) {
                return $next($request);
            }
            return response()->json(['message' => 'Sesi telah berakhir. Silakan login kembali.'], 401);
        }

        if ((int) $tokenVersion !== (int) $user->token_version) {
            return response()->json(['message' => 'Sesi telah berakhir. Silakan login kembali.'], 401);
        }

        return $next($request);
    }
}
