<?php

namespace App\Http\Controllers;

use App\Models\PengajuanTuk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PengajuanTukController extends Controller
{
    public function submit(Request $request)
    {
        $request->validate([
            'data' => 'required|json',
            'dokumenPermohonan' => 'required|file|mimes:pdf|max:10240',
            'lampiran' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = json_decode($request->input('data'), true);

        if (!in_array($data['tukType'] ?? '', ['mandiri', 'sewaktu'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tipe TUK tidak valid. Harus "mandiri" atau "sewaktu".',
            ], 422);
        }

        $tukType = $data['tukType'];

        // Upload dokumen
        $dokumenPath = $request->file('dokumenPermohonan')->store('pengajuan-tuk', 'str_moon');
        $lampiranPath = null;
        if ($request->hasFile('lampiran')) {
            $lampiranPath = $request->file('lampiran')->store('pengajuan-tuk', 'str_moon');
        }

        // Generate reference number
        $refNumber = 'TUK-' . strtoupper(Str::random(8)) . '-' . strtoupper(Str::random(4));

        $record = PengajuanTuk::create([
            'ref_number' => $refNumber,
            'tuk_type' => $tukType,
            'status' => 'diajukan',
            'data_tuk' => $data['dataTuk'] ?? [],
            'asesor_list' => $data['asesorList'] ?? [],
            'data_sewaktu' => $data['dataSewaktu'] ?? null,
            'dokumen_permohonan' => $dokumenPath,
            'lampiran' => $lampiranPath,
            'submitted_by' => $request->user()?->id,
        ]);

        $moonBase = 'https://moon.lspgatensi.id/files/new_gatensi/';

        return response()->json([
            'success' => true,
            'refNumber' => $refNumber,
            'documentLinks' => [
                'dokumenPermohonan' => $moonBase . $dokumenPath,
                'lampiran' => $lampiranPath ? $moonBase . $lampiranPath : null,
            ],
        ], 201);
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $query = PengajuanTuk::with('submitter:id,name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ref_number', 'like', "%{$search}%")
                  ->orWhere('tuk_type', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($tukType = $request->query('tuk_type')) {
            $query->where('tuk_type', $tukType);
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function show($id)
    {
        $record = PengajuanTuk::with('submitter:id,name')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $record,
        ]);
    }
}
