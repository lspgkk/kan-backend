<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SingleSessionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tokenVersion = auth()->payload()->get('token_version');

        Log::debug('SingleSessionMiddleware', [
            'user_id'                  => $user->id,
            'email'                    => $user->email,
            'token_version_from_claim' => $tokenVersion,
            'token_version_from_db'    => $user->token_version,
            'url'                      => $request->path(),
        ]);

        // Token without token_version claim — allow only if DB version is still default (pre-migration)
        if ($tokenVersion === null) {
            if ((int) $user->token_version === 0) {
                return $next($request);
            }
            Log::warning('SingleSessionMiddleware REJECTED (null claim)', [
                'user_id' => $user->id,
                'db_version' => $user->token_version,
            ]);
            return response()->json(['message' => 'Sesi telah berakhir. Silakan login kembali.'], 401);
        }

        if ((int) $tokenVersion !== (int) $user->token_version) {
            Log::info('SingleSessionMiddleware REJECTED (version mismatch)', [
                'user_id'    => $user->id,
                'claim'      => (int) $tokenVersion,
                'db'         => (int) $user->token_version,
            ]);
            return response()->json(['message' => 'Sesi telah berakhir. Silakan login kembali.'], 401);
        }

        return $next($request);
    }
}
