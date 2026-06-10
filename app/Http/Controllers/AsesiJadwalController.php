<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AsesiJadwalController extends Controller
{
    /**
     * Get paginated list of id_izins with nama, jadwal_id, and tanggal_uji.
     * Supports search, filter, and pagination.
     */
    public function listIdIzins(Request $request)
    {
        $query = AsesiJadwal::select([
                'asesi_jadwals.id_izin',
                'personals.nama',
                'asesi_jadwals.jadwal_id',
                'jadwals.tanggal_uji',
            ])
            ->join('personals', 'asesi_jadwals.id_izin', '=', 'personals.id_izin')
            ->join('jadwals', 'asesi_jadwals.jadwal_id', '=', 'jadwals.jadwal_id');

        // ── Search ──
        if ($search = trim($request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('personals.nama', 'like', "%{$search}%")
                  ->orWhere('asesi_jadwals.id_izin', 'like', "%{$search}%")
                  ->orWhere('asesi_jadwals.jadwal_id', 'like', "%{$search}%");
            });
        }

        // ── Filters ──
        if ($request->filled('id_izin')) {
            $query->where('asesi_jadwals.id_izin', $request->id_izin);
        }

        if ($request->filled('jadwal_id')) {
            $query->where('asesi_jadwals.jadwal_id', $request->jadwal_id);
        }

        if ($request->filled('tanggal_uji_from')) {
            $query->whereDate('jadwals.tanggal_uji', '>=', $request->tanggal_uji_from);
        }

        if ($request->filled('tanggal_uji_to')) {
            $query->whereDate('jadwals.tanggal_uji', '<=', $request->tanggal_uji_to);
        }

        // ── Sort ──
        $sortBy = $request->input('sort_by', 'jadwals.tanggal_uji');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['jadwals.tanggal_uji', 'personals.nama', 'asesi_jadwals.id_izin', 'asesi_jadwals.jadwal_id'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'jadwals.tanggal_uji';
        }
        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');

        // ── Pagination ──
        $perPage = min((int) $request->input('per_page', 20), 100);
        $data = $query->paginate($perPage);

        return response()->json([
            'message' => 'Data retrieved successfully',
            'data' => $data,
        ]);
    }
}
