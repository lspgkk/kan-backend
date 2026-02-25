<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\User;
use Illuminate\Http\Request;

class KomtekController extends Controller
{
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $komteks = User::where('role_id', 4)->select(['id', 'name'])->get();

            return response()->json([
                'status' => 'success',
                'data' => $komteks,
                'meta' => [
                    'all' => true,
                    'total' => $komteks->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $komteks = User::where('role_id', 4)->select(['id', 'name'])->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $komteks,
        ]);
    }

    public function rekomendasi(Request $request, $id)
    {
        $validated = $request->validate([
            'kompeten' => 'required|boolean',
        ]);

        AsesiJadwal::where('id_izin', $id)->update([
            'kompeten' => (bool) $validated['kompeten'],
        ]);

        return response()->json([
            'message' => 'success',
        ]);
    }
}
