<?php

namespace App\Http\Controllers;

use App\Models\SurveyPertanyaan;
use App\Models\SurveyResponse;
use App\Models\SurveyResponseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SurveyController extends Controller
{
    // ========== MASTER DATA PERTANYAAN ==========

    public function indexPertanyaan(Request $request)
    {
        $query = SurveyPertanyaan::query();

        if ($request->has('kategori')) {
            $query->where('kategori', strtoupper($request->kategori));
        }

        $pertanyaans = $query->orderBy('kategori')->orderBy('nomor')->get();

        return response()->json([
            'status' => 'success',
            'data' => $pertanyaans,
        ]);
    }

    public function showPertanyaan($id)
    {
        $pertanyaan = SurveyPertanyaan::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $pertanyaan,
        ]);
    }

    public function storePertanyaan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kategori' => 'required|in:LSP,TUK',
            'nomor' => 'required|integer|min:1',
            'aspek' => 'required|string|max:255',
            'pertanyaan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $exists = SurveyPertanyaan::where('kategori', $request->kategori)
            ->where('nomor', $request->nomor)
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => "Nomor {$request->nomor} already exists for kategori {$request->kategori}",
            ], 422);
        }

        $pertanyaan = SurveyPertanyaan::create($request->only(['kategori', 'nomor', 'aspek', 'pertanyaan']));

        return response()->json([
            'status' => 'success',
            'data' => $pertanyaan,
        ], 201);
    }

    public function updatePertanyaan(Request $request, $id)
    {
        $pertanyaan = SurveyPertanyaan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'kategori' => 'sometimes|in:LSP,TUK',
            'nomor' => 'sometimes|integer|min:1',
            'aspek' => 'sometimes|string|max:255',
            'pertanyaan' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pertanyaan->update($request->only(['kategori', 'nomor', 'aspek', 'pertanyaan']));

        return response()->json([
            'status' => 'success',
            'data' => $pertanyaan,
        ]);
    }

    public function destroyPertanyaan($id)
    {
        $pertanyaan = SurveyPertanyaan::findOrFail($id);
        $pertanyaan->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pertanyaan deleted successfully',
        ]);
    }

    // ========== SURVEY SUBMISSION ==========

    public function index(Request $request)
    {
        $query = SurveyResponse::with('details.pertanyaan');

        if ($request->filled('kategori')) {
            $query->where('kategori', strtoupper($request->kategori));
        }

        if ($request->filled('id_izin')) {
            $query->where('id_izin', $request->id_izin);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id_izin', 'like', "%{$search}%")
                  ->orWhere('saran', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 15);
        $surveys = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Batch-load TUK info for all id_izin in this page
        $idIzins = $surveys->getCollection()->pluck('id_izin')->unique()->values();
        $tukLookup = [];
        if ($idIzins->isNotEmpty()) {
            $klasifikasiList = \App\Models\KlasifikasiKualifikasi::with('tuk')
                ->whereIn('id_izin', $idIzins)
                ->get();

            foreach ($klasifikasiList as $kk) {
                $tukRelation = $kk->relationLoaded('tuk') ? $kk->getRelation('tuk') : null;
                $tukLookup[$kk->id_izin] = [
                    'tuk_id'   => $kk->tuk,
                    'tuk_name' => $tukRelation?->nama_tuk,
                ];
            }
        }

        $surveys->getCollection()->transform(function ($response) use ($tukLookup) {
            $totalSkor = $response->details->sum('skor');
            $count = $response->details->count();
            $tuk = $tukLookup[$response->id_izin] ?? null;
            return [
                'id'          => $response->id,
                'id_izin'     => $response->id_izin,
                'kategori'    => $response->kategori,
                'saran'       => $response->saran,
                'pernyataan'  => $response->pernyataan,
                'rata_rata'   => $count > 0 ? round($totalSkor / $count, 2) : 0,
                'tuk'         => $tuk,
                'details'     => $response->details->map(function ($detail) {
                    return [
                        'pertanyaan_id' => $detail->pertanyaan_id,
                        'aspek'         => $detail->pertanyaan?->aspek,
                        'pertanyaan'    => $detail->pertanyaan?->pertanyaan,
                        'skor'          => $detail->skor,
                    ];
                }),
                'created_at'  => $response->created_at,
                'updated_at'  => $response->updated_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $surveys,
        ]);
    }

    public function submit(Request $request, $id_izin)
    {
        $validator = Validator::make($request->all(), [
            'LSP' => 'sometimes|array',
            'LSP.*.pertanyaan_id' => 'required_with:LSP|integer|exists:survey_pertanyaans,id',
            'LSP.*.skor' => 'required_with:LSP|integer|min:1|max:5',
            'TUK' => 'sometimes|array',
            'TUK.*.pertanyaan_id' => 'required_with:TUK|integer|exists:survey_pertanyaans,id',
            'TUK.*.skor' => 'required_with:TUK|integer|min:1|max:5',
            'saran' => 'nullable|string',
            'pernyataan' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$request->has('LSP') && !$request->has('TUK')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Provide at least one of LSP or TUK answers',
            ], 422);
        }

        $saran = $request->saran;
        $pernyataan = $request->pernyataan;
        $results = [];

        foreach (['LSP', 'TUK'] as $kategori) {
            if (!$request->has($kategori)) {
                continue;
            }

            $answers = $request->input($kategori);

            // Verify all pertanyaan_ids belong to this kategori
            $validIds = SurveyPertanyaan::where('kategori', $kategori)
                ->whereIn('id', collect($answers)->pluck('pertanyaan_id'))
                ->pluck('id')
                ->toArray();

            $invalidIds = collect($answers)
                ->pluck('pertanyaan_id')
                ->diff($validIds);

            if ($invalidIds->isNotEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Invalid pertanyaan_id for kategori {$kategori}: " . $invalidIds->join(', '),
                ], 422);
            }

            // Upsert response header
            $response = SurveyResponse::updateOrCreate(
                ['id_izin' => $id_izin, 'kategori' => $kategori],
                ['saran' => $saran, 'pernyataan' => $pernyataan]
            );

            // Replace detail answers
            $response->details()->delete();

            foreach ($answers as $item) {
                $response->details()->create([
                    'pertanyaan_id' => $item['pertanyaan_id'],
                    'skor' => $item['skor'],
                ]);
            }

            $response->load('details.pertanyaan');
            $results[$kategori] = $response;
        }

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ], 201);
    }

    public function show($id_izin)
    {
        $responses = SurveyResponse::with('details.pertanyaan')
            ->where('id_izin', $id_izin)
            ->get();

        if ($responses->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No survey found for this id_izin',
            ], 404);
        }

        // Get TUK info from KlasifikasiKualifikasi for this asesi
        $tukInfo = null;
        $klasifikasiKualifikasi = \App\Models\KlasifikasiKualifikasi::with('tuk')
            ->where('id_izin', $id_izin)
            ->first();

        if ($klasifikasiKualifikasi) {
            $tukRelation = $klasifikasiKualifikasi->relationLoaded('tuk')
                ? $klasifikasiKualifikasi->getRelation('tuk')
                : null;
            $tukInfo = [
                'tuk_id'   => $klasifikasiKualifikasi->tuk,
                'tuk_name' => $tukRelation?->nama_tuk,
            ];
        }

        $data = [];
        foreach ($responses as $response) {
            $data[$response->kategori] = [
                'saran'      => $response->saran,
                'pernyataan' => $response->pernyataan,
                'answers'    => $response->details->map(function ($detail) {
                    return [
                        'pertanyaan_id' => $detail->pertanyaan_id,
                        'aspek'         => $detail->pertanyaan?->aspek,
                        'pertanyaan'    => $detail->pertanyaan?->pertanyaan,
                        'skor'          => $detail->skor,
                    ];
                }),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_izin' => $id_izin,
                'tuk'     => $tukInfo,
                'surveys' => $data,
            ],
        ]);
    }
}
