<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tukId = $request->get('tuk_id');
        $from = $request->get('from');
        $until = $request->get('until');

        // --- Date Range ---
        if ($from && $until) {
            try {
                $start = Carbon::parse($from, 'Asia/Jakarta')->startOfDay()->setTimezone('UTC');
                $end = Carbon::parse($until, 'Asia/Jakarta')->endOfDay()->setTimezone('UTC');
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Invalid date format. Please use YYYY-MM-DD or valid timestamp.',
                ], 400);
            }
        } else {
            $now = Carbon::now('Asia/Jakarta');
            $start = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay()->setTimezone('UTC');
            $end = $now->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay()->setTimezone('UTC');
        }

        $user = auth()->user();

        $allowedTuks = null;

        $allTuk = DB::table('mytuk as tuk')
            ->where('id_lsp', 16)
            ->when($tukId, fn($q) => $q->where('tuk.id', $tukId))
            ->when($allowedTuks, fn($q) => $q->whereIn('tuk.id', $allowedTuks))
            ->select([
                'tuk.id as id_tuk',
                'tuk.nama_tuk as nama_tuk',
                'tuk.latitude',
                'tuk.longitude',
            ])
            ->get();
        // =======================================================
        // 🔸 RESPONSE
        // =======================================================
        $response = [
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'tuk_count' => $allTuk->count(),
                'skk_count' => 2054,
                'uji_count' => 75,
                'tuk' => $allTuk,
            ]
        ];
        return response()->json($response);
    }
}
