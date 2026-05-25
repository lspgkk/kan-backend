<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaticApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $staticKey = config('services.pengajuan_tuk_api_key');

        if (!$staticKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key tidak dikonfigurasi',
            ], 500);
        }

        $providedKey = $request->header('X-API-Key') ?? $request->query('api_key');

        if (!$providedKey || $providedKey !== $staticKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key tidak valid',
            ], 401);
        }

        return $next($request);
    }
}
