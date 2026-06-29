<?php

namespace App\Http\Controllers;

use App\Models\JabatanDokumen;
use App\Models\KanSoal;
use App\Models\Kuk;
use App\Models\UnitKompetensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KanSoalController extends Controller
{
    /**
     * GET /api/kan/soal
     * List soal KAN, filter by dokumen_id and/or tipe
     */
    public function index(Request $request)
    {
        $query = KanSoal::query()
            ->with(['unit:id,kode', 'kuk:id,kode']);

        if ($request->filled('dokumen_id')) {
            $query->where('id_dokumen', $request->integer('dokumen_id'));
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->integer('tipe'));
        }

        $query->orderBy('no', 'asc');

        return response()->json([
            'message' => 'OK',
            'data'    => $query->get(),
        ]);
    }

    /**
     * POST /api/kan/soal
     * Create a single KAN soal
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_dokumen' => 'required|integer',
            'tipe'       => 'required|integer|in:1,3,8',
            'soal'       => 'required|string',
            'no'         => 'required|integer',
            'jawab_a'    => 'nullable|string',
            'jawab_b'    => 'nullable|string',
            'jawab_c'    => 'nullable|string',
            'jawab_d'    => 'nullable|string',
            'jawaban'    => 'nullable|string|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $soal = KanSoal::create($request->all());

        return response()->json([
            'message' => 'Soal KAN created',
            'data'    => $soal->load(['unit:id,kode', 'kuk:id,kode']),
        ], 201);
    }

    /**
     * GET /api/kan/soal/{id}
     */
    public function show($id)
    {
        $soal = KanSoal::with(['unit:id,kode', 'kuk:id,kode'])->find($id);

        if (!$soal) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json([
            'message' => 'OK',
            'data'    => $soal,
        ]);
    }

    /**
     * PUT /api/kan/soal/{id}
     */
    public function update(Request $request, $id)
    {
        $soal = KanSoal::find($id);

        if (!$soal) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'id_dokumen' => 'sometimes|required|integer',
            'tipe'       => 'sometimes|required|integer|in:1,3,8',
            'soal'       => 'sometimes|required|string',
            'no'         => 'sometimes|required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $soal->update($request->all());

        return response()->json([
            'message' => 'Soal KAN updated',
            'data'    => $soal->fresh()->load(['unit:id,kode', 'kuk:id,kode']),
        ]);
    }

    /**
     * DELETE /api/kan/soal/{id}
     */
    public function destroy($id)
    {
        $soal = KanSoal::find($id);

        if (!$soal) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $soal->delete();

        return response()->json(['message' => 'Soal KAN deleted']);
    }

    /**
     * POST /api/kan/soal/bulk
     * Bulk insert or update soal KAN.
     * Accepts array of soal objects. If id is provided, update; otherwise create.
     */
    public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'soals'          => 'required|array',
            'soals.*.id_dokumen' => 'required|integer',
            'soals.*.tipe'   => 'required|integer|in:1,3,8',
            'soals.*.soal'   => 'required|string',
            'soals.*.no'     => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $result = [];

        foreach ($request->input('soals') as $item) {
            if (!empty($item['id'])) {
                $soal = KanSoal::find($item['id']);
                if ($soal) {
                    $soal->update($item);
                    $result[] = $soal->fresh();
                    continue;
                }
            }
            $result[] = KanSoal::create($item);
        }

        return response()->json([
            'message' => count($result) . ' soal KAN processed',
            'data'    => $result,
        ]);
    }

    // ──────────────────────────────────────────────
    //  REFERENCE DATA for frontend dropdowns
    // ──────────────────────────────────────────────

    /**
     * GET /api/kan/ref/unit-kompetensi/{id_jabatan}
     * Daftar Unit Kompetensi untuk suatu jabatan kerja (skema)
     */
    public function refUnitKompetensi($idJabatan)
    {
        $units = UnitKompetensi::select('id', 'kode', 'nama')
            ->where('id_jabatan', $idJabatan)
            ->orderBy('kode', 'asc')
            ->get();

        return response()->json([
            'message' => 'OK',
            'data'    => $units,
        ]);
    }

    /**
     * GET /api/kan/ref/kuk/{id_unit}
     * Daftar KUK untuk suatu unit kompetensi (via subunit)
     */
    public function refKuk($idUnit)
    {
        $kukList = Kuk::select('kuks.id', 'kuks.kode', 'kuks.nama', 'subunit_kompetensis.kode as subunit_kode')
            ->join('subunit_kompetensis', 'kuks.id_subunitkompetensi', '=', 'subunit_kompetensis.id')
            ->where('subunit_kompetensis.id_unitkompetensi', $idUnit)
            ->orderBy('kuks.kode', 'asc')
            ->get();

        return response()->json([
            'message' => 'OK',
            'data'    => $kukList,
        ]);
    }

    /**
     * GET /api/kan/ref/dokumen/{id_jabatan}
     * Daftar dokumen (jabatan_dokumens) untuk KAN
     * Filter by kode FR (FR.IA.04.B, FR.IA.05, FR.IA.06, FR.AK.02)
     */
    public function refDokumen($idJabatan)
    {
        $dokumen = JabatanDokumen::select('id', 'kode', 'nama_dokumen')
            ->where('id_jabatan', $idJabatan)
            ->whereIn('kode', ['FR.IA.04.B', 'FR.IA.05', 'FR.IA.06', 'FR.AK.02'])
            ->where('is_active', true)
            ->orderBy('kode', 'asc')
            ->get();

        return response()->json([
            'message' => 'OK',
            'data'    => $dokumen,
        ]);
    }

    /**
     * GET /api/kan/ref/skema/{id_jabatan}
     * Semua referensi untuk suatu skema (unit kompetensi + kuk + dokumen)
     */
    public function refSkema($idJabatan)
    {
        $units = UnitKompetensi::select('id', 'kode', 'nama')
            ->where('id_jabatan', $idJabatan)
            ->orderBy('kode', 'asc')
            ->get()
            ->map(function ($unit) {
                $unit->kuk_list = Kuk::select('kuks.id', 'kuks.kode', 'kuks.nama', 'subunit_kompetensis.kode as subunit_kode')
                    ->join('subunit_kompetensis', 'kuks.id_subunitkompetensi', '=', 'subunit_kompetensis.id')
                    ->where('subunit_kompetensis.id_unitkompetensi', $unit->id)
                    ->orderBy('kuks.kode', 'asc')
                    ->get();
                return $unit;
            });

        $dokumen = JabatanDokumen::select('id', 'kode', 'nama_dokumen')
            ->where('id_jabatan', $idJabatan)
            ->whereIn('kode', ['FR.IA.04.B', 'FR.IA.05', 'FR.IA.06', 'FR.AK.02'])
            ->where('is_active', true)
            ->orderBy('kode', 'asc')
            ->get();

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'unit_kompetensi' => $units,
                'dokumen'         => $dokumen,
            ],
        ]);
    }
}
