<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Incident;
use App\Models\Branch;
use App\Models\FrtDetail;
use App\Models\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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

        // if ($user && !$user->hasRole('superadmin')) {
        //     // Only branches assigned to the user
        //     $allowedTuks = $user->branches()->pluck('branch_id');

        //     if ($allowedTuks->isEmpty()) {
        //         return response()->json([
        //             'message' => 'Dashboard data retrieved successfully',
        //             'data' => [
        //                 'alarm_locations' => [],
        //             ]
        //         ]);
        //     }
        // }

        // if ($tukId) {
        //     $incidentQuery->where('branch_id', $tukId);
        // }

        $alarmLocations = DB::table('mytuk as tuk')
            ->when($tukId, fn($q) => $q->where('tuk.id', $tukId))
            ->when($allowedTuks, fn($q) => $q->whereIn('tuk.id', $allowedTuks))
            ->select([
                'tuk.id as id_tuk',
                'tuk.nama as nama_tuk',
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
                'alarm_locations' => $alarmLocations,
            ]
        ];
        return response()->json($response);
    }
}
