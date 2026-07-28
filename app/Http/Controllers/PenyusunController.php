<?php

namespace App\Http\Controllers;

use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\KanPraktisiJabatan;
use App\Models\Referensi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PenyusunController extends Controller
{
    use KanRoleDashboardTrait;

    // ──────────────────────────────────────────────
    //  SKEMA PESERTA: Participants per Skema
    // ──────────────────────────────────────────────

    public function skemaPeserta($idSkema): JsonResponse
    {
        $config = JabatanConfigA::with('jabatan:id_jabatan_kerja,jabatan_kerja')
            ->where('id_jabatan', $idSkema)->firstOrFail();

        $jabatanNama = $config->jabatan?->jabatan_kerja;

        // Get praktisi from KanPraktisiJabatan (KAN flow)
        $peserta = KanPraktisiJabatan::with('user:id,name,email,phone,noreg')
            ->where('id_jabatan_kerja', $config->id_jabatan)
            ->get();

        $kanIds = $peserta->pluck('id');

        // Batch load Barcode TTD status per kan_praktisi_jabatan_id
        $barcodes = Barcode::select(['kan_praktisi_jabatan_id', 'jenis_dokumen', 'url_image'])
            ->whereIn('kan_praktisi_jabatan_id', $kanIds)
            ->whereIn('jenis_dokumen', ['IA04B', 'IA05', 'IA06'])
            ->get()
            ->groupBy('kan_praktisi_jabatan_id');

        $result = $peserta->map(function ($p) use ($barcodes, $jabatanNama) {
            $docs = $barcodes[$p->id] ?? collect();

            $ia04b = $docs->firstWhere('jenis_dokumen', 'IA04B');
            $ia05  = $docs->firstWhere('jenis_dokumen', 'IA05');
            $ia06  = $docs->firstWhere('jenis_dokumen', 'IA06');

            $ia04bStatus = $ia04b && $ia04b->url_image ? 'Terisi' : 'Belum';
            $ia05Status  = $ia05  && $ia05->url_image  ? 'Terisi' : 'Belum';
            $ia06Status  = $ia06  && $ia06->url_image  ? 'Terisi' : 'Belum';

            return [
                'id'             => $p->id,
                'id_izin'        => $p->user?->noreg ?? '',
                'nama'           => $p->user?->name ?? '-',
                'jabker'         => $jabatanNama,
                'ia04b_status'   => $ia04bStatus,
                'ia05_status'    => $ia05Status,
                'ia06_status'    => $ia06Status,
                'selesai'        => $ia06Status === 'Terisi',
            ];
        });

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'skema'    => [
                    'id'            => $config->id_jabatan,
                    'jabatan_kerja' => $jabatanNama,
                    'kan_no_skema'  => $config->kan_no_skema,
                ],
                'peserta'  => $result,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  IA04B: Get & Submit (delegates to KanAsesmenController)
    // ──────────────────────────────────────────────

    public function getIa04b($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa04b($idIzin);
    }

    public function submitIa04b(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->submitKanIa04b($request, $idIzin);
    }

    // ──────────────────────────────────────────────
    //  IA05: Get & Submit
    // ──────────────────────────────────────────────

    public function getIa05($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa05($idIzin);
    }

    public function submitIa05(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->submitKanIa05($request, $idIzin);
    }

    // ──────────────────────────────────────────────
    //  IA06: Get & Submit
    // ──────────────────────────────────────────────

    public function getIa06($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa06($idIzin);
    }

    public function submitIa06(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->submitKanIa06($request, $idIzin);
    }

    // ──────────────────────────────────────────────
    //  MUK: Hasil MUK Participant
    // ──────────────────────────────────────────────

    public function getMuk($idIzin): JsonResponse
    {
        $personal = Personal::select(['id_izin', 'nama'])->where('id_izin', $idIzin)->first();
        if (!$personal) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $asesiJadwal = AsesiJadwal::where('id_izin', $idIzin)->first();
        $jadwal = $asesiJadwal
            ? Jadwal::select(['jadwal_id', 'tanggal_uji', 'tanggal_selesai'])
                ->where('jadwal_id', $asesiJadwal->jadwal_id)
                ->first()
            : null;

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $idIzin)->first();

        // Get MUK TTD status (penyusun, validator, manajer)
        $config = $klasifikasi
            ? JabatanConfigA::select([
                'kan_penyusun_id', 'kan_penyusun_tgl', 'kan_penyusun_img',
                'kan_validator_id', 'kan_validator_tgl', 'kan_validator_img',
                'kan_manajer_id', 'kan_manajer_tgl', 'kan_manajer_img',
                'kan_no_skema',
            ])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->first()
            : null;

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'id_izin'        => $idIzin,
                'nama'           => $personal->nama,
                'tanggal_uji'    => $jadwal?->tanggal_uji,
                'tanggal_selesai'=> $jadwal?->tanggal_selesai,
                'kan_no_skema'   => $config?->kan_no_skema,
                'ttd'            => [
                    'penyusun'   => [
                        'signed'  => !empty($config?->kan_penyusun_img),
                        'tanggal' => $config?->kan_penyusun_tgl,
                    ],
                    'validator'  => [
                        'signed'  => !empty($config?->kan_validator_img),
                        'tanggal' => $config?->kan_validator_tgl,
                    ],
                    'manajer'    => [
                        'signed'  => !empty($config?->kan_manajer_img),
                        'tanggal' => $config?->kan_manajer_tgl,
                    ],
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  TTD: Generate QR Code & Save to JabatanConfigA
    // ──────────────────────────────────────────────

    public function ttd(Request $request, $idIzin): JsonResponse
    {
        $user = auth()->user();

        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();
        if (!$klasifikasi) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
        $filename = 'ttd_penyusun_' . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // Save to JabatanConfigA
        JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)->update([
            'kan_penyusun_id'  => $user->id,
            'kan_penyusun_tgl' => Carbon::now()->toDateString(),
            'kan_penyusun_img' => $urlImage,
            'kan_penyusun_url' => $urlTtd,
        ]);

        // Also save to Barcode for verification
        Barcode::updateOrCreate(
            [
                'id_user'       => $user->id,
                'id_izin'       => $idIzin,
                'jenis_dokumen' => 'PENYUSUN',
            ],
            [
                'url_image'  => $urlImage,
                'url_ttd'    => $urlTtd,
                'id_role'    => $user->role_id,
                'created_at' => Carbon::now()->toDateString(),
            ]
        );

        return response()->json([
            'message'  => 'TTD saved',
            'data'     => [
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'tanggal'   => Carbon::now()->toDateString(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  MAPA01 / MAPA02 Data (per jabker — full content)
    // ──────────────────────────────────────────────

    public function getMapaData(string $jabkerId, string $jenis): JsonResponse
    {
        $docType = $jenis === 'mapa01' ? 'MAPA01' : 'MAPA02';

        $jabatanKerja = JabatanKerja::find($jabkerId);
        if (!$jabatanKerja) {
            return response()->json(['message' => 'Jabatan kerja not found'], 404);
        }

        $config = JabatanConfigA::with([
            'kanPenyusun:id,name,noreg',
            'kanValidator:id,name,noreg',
            'kanManajer:id,name,noreg',
            'manajer:id,name,noreg',
        ])->where('id_jabatan', $jabkerId)->first();

        $dokumen = JabatanDokumen::with(['kelompoks.units.unit'])
            ->where('id_jabatan', $jabkerId)
            ->where('kode', $docType)
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json(['message' => "$docType not found for this jabker"], 404);
        }

        $jenjang = (int) ($jabatanKerja->jenjang_id ?? 0);

        // ── Kelompok Kerja ──
        $kelompokKerja = [
            'id'             => $dokumen->id,
            'kode'           => $dokumen->kode,
            'nama_dokumen'   => $dokumen->nama_dokumen,
            'kelompok_kerja' => $dokumen->kelompoks->map(fn ($kg) => [
                'id'    => $kg->id,
                'nama'  => $kg->nama,
                'urut'  => $kg->urut,
                'units' => $kg->units->map(fn ($u) => [
                    'id_unit'   => $u->id_unit,
                    'nama_unit' => $u->unit?->nama,
                    'kode_unit' => $u->unit?->kode,
                ])->values(),
            ])->values(),
        ];

        // ── Referensi ──
        if ($jenis === 'mapa02') {
            // MAPA02: flat structure (matches PraAsesmenController@buildMapa02Data)
            // KAN-specific default instrument patterns (same as buildKanMapa2DataByPraktisi)
            $kanMapa02Defaults = [
                'FR.IA.04B' => 2,  // DIT
                'FR.IA.05'  => 2,  // DPT PG
                'FR.IA.06'  => 2,  // DPT Esai
            ];

            $referensis = Referensi::select(['id', 'nama', 'kategori', 'isdefault'])
                ->whereIn('kategori', ['MAPA02-1', 'MAPA02_1'])
                ->get();

            $dataReferensi = $referensis
                ->groupBy('kategori')
                ->map(function ($items, $kategori) use ($kanMapa02Defaults) {
                    return [
                        'kategori'   => $kategori,
                        'referensis' => $items->map(function ($item) use ($kategori, $kanMapa02Defaults) {
                            $isdefault = null;
                            $potensiAsesiIndex = null;

                            foreach ($kanMapa02Defaults as $pattern => $idx) {
                                if (str_contains($item->nama, $pattern)) {
                                    $isdefault = 1;
                                    $potensiAsesiIndex = $idx;
                                    break;
                                }
                            }

                            return [
                                'id'                  => $item->id,
                                'nama'                => $item->nama,
                                'isdefault'           => $isdefault,
                                'potensi_asesi_index' => $potensiAsesiIndex,
                            ];
                        })->values(),
                    ];
                })
                ->values();
        } else {
            // MAPA01: nested kelompok→kategori→subkategori→referensis (matches PraAsesmenController@buildMapa01Data)
            // Default checked values — same as buildKanMapaDataByPraktisi (KAN observasi defaults)
            $kanMapa01Defaults = [
                82  => true,  // hasil pelatihan mampu telusur
                87  => true,  // sertifikasi
                91  => true,  // tempat kerja nyata
                93  => true,  // tersedia
                98  => true,  // LSP Gatensi Karya Konstruksi
                101 => true,  // manajer sertifikasi LSP
                105 => true,  // standar kompetensi (SKKNI)
                114 => true,  // peluang kegiatan asesmen terintegrasi
            ];

            $referensis = Referensi::with(['kategoriRel', 'kelompokRel', 'subkategoriRel'])
                ->select(['id', 'nama', 'id_kelompok', 'id_kategori', 'id_subkategori'])
                ->where('kategori', $docType)
                ->whereNotNull('id_kelompok')
                ->get()
                ->filter(fn ($r) => $r->kelompokRel !== null);

            $dataReferensi = $referensis
                ->groupBy('id_kelompok')
                ->values()
                ->map(function ($kelompokItems) use ($kelompokKerja, $kanMapa01Defaults) {
                    $kelompok = $kelompokItems->first()->kelompokRel;
                    return [
                        'kelompok' => [
                            'id'   => $kelompok->id,
                            'nama' => $kelompok->nama,
                            'urut' => $kelompok->urut,
                            'kategoris' => $kelompokItems
                                ->groupBy(fn ($i) => $i->id_kategori ?? 'tanpa_kategori')
                                ->values()
                                ->map(function ($kategoriItems) use ($kanMapa01Defaults) {
                                    $kategori = $kategoriItems->first()->kategoriRel;
                                    return [
                                        'id'           => $kategori?->id,
                                        'kategori'     => $kategori?->kategori,
                                        'nama'         => $kategori?->nama,
                                        'urut'         => $kategori?->urut,
                                        'id_kelompok'  => $kategori?->id_kelompok,
                                        'subkategoris' => $kategoriItems
                                            ->groupBy(fn ($i) => $i->id_subkategori ?? 'tanpa_subkategori')
                                            ->values()
                                            ->map(function ($subItems) use ($kanMapa01Defaults) {
                                                $sub = $subItems->first()->subkategoriRel;
                                                return [
                                                    'id'         => $sub?->id,
                                                    'nama'       => $sub?->nama ?? 'Tanpa Subkategori',
                                                    'urut'       => $sub?->urut,
                                                    'referensis' => $subItems->map(fn ($item) => [
                                                        'id'     => $item->id,
                                                        'nama'   => $item->nama,
                                                        'value'  => $kanMapa01Defaults[$item->id] ?? false,
                                                        'alasan' => '',
                                                    ])->values(),
                                                ];
                                            })->values(),
                                    ];
                                })->values(),
                        ],
                    ];
                });

            // ── Inject alasan (kode unit) ke item kelompok 3 (modifikasi) — same as PraAsesmen ──
            $unitText = '';
            foreach ($kelompokKerja['kelompok_kerja'] as $kg) {
                $unitText .= '<b>' . e($kg['nama']) . ":</b><br>\n";
                foreach ($kg['units'] as $unit) {
                    $unitText .= '&nbsp;&nbsp;- ' . e($unit['kode_unit'] ?? '') . "<br>\n";
                }
            }
            $dataReferensi = $dataReferensi->map(function ($kelRef) use ($unitText) {
                if (isset($kelRef['kelompok']['id']) && $kelRef['kelompok']['id'] == 3) {
                    foreach ($kelRef['kelompok']['kategoris'] as &$kat) {
                        foreach ($kat['subkategoris'] as &$sub) {
                            foreach ($sub['referensis'] as &$ref) {
                                if (!empty($ref['value'])) {
                                    $ref['alasan'] = $unitText;
                                }
                            }
                        }
                    }
                }
                return $kelRef;
            })->values();
        }

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'kelompok_kerja'  => $kelompokKerja,
                'referensi_form'  => $dataReferensi,
                'skkni'           => $jabatanKerja->acuan ?? '',
                'jenjang'         => $jenjang,
                'metode'          => null,
                'barcodes'        => [],
                // Extra header info for penyusun display
                'judul'           => $jabatanKerja->jabatan_kerja ?? '',
                'nomor'           => $config?->kan_no_skema ?? $config?->no_skema ?? '',
                'penyusun_info'   => $config ? [
                    'nama'    => $config->kanPenyusun?->name,
                    'noreg'   => $config->kanPenyusun?->noreg,
                    'tanggal' => $config->kan_penyusun_tgl,
                    'barcode' => $config->kan_penyusun_img,
                ] : null,
                'validator_info'  => $config ? [
                    'nama'    => $config->kanValidator?->name,
                    'noreg'   => $config->kanValidator?->noreg,
                    'tanggal' => $config->kan_validator_tgl,
                    'barcode' => $config->kan_validator_img,
                ] : null,
                'manajer_info'    => $config ? [
                    'nama'    => $config->kanManajer?->name ?? $config->manajer?->name,
                    'tanggal' => $config->kan_manajer_tgl ?? $config->penyusun_tgl,
                    'barcode' => $config->kan_manajer_img ?? $config->manajer_img,
                ] : null,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  MAPA01 / MAPA02 TTD (per jabker)
    // ──────────────────────────────────────────────

    public function ttdMapaByJabker(Request $request, string $jabkerId, string $jenis): JsonResponse
    {
        if ($jenis === 'mapa01') {
            return $this->generateMapaTtd($request, $jabkerId, 'MAPA01_JABKER', 'ttd_mapa01_jabker');
        }
        return $this->generateMapaTtd($request, $jabkerId, 'MAPA02_JABKER', 'ttd_mapa02_jabker');
    }

    public function statusMapaTtd(string $jabkerId, string $jenis): JsonResponse
    {
        $docType = $jenis === 'mapa01' ? 'MAPA01_JABKER' : 'MAPA02_JABKER';
        $user = auth()->user();

        $barcode = Barcode::where('id_izin', $jabkerId)
            ->where('jenis_dokumen', $docType)
            ->whereNotNull('url_image')
            ->first();

        if (!$barcode) {
            return response()->json(['message' => 'OK', 'data' => ['sudah' => false]]);
        }

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'sudah'     => true,
                'url_image' => $barcode->url_image,
                'tanggal'   => $barcode->created_at ? Carbon::parse($barcode->created_at)->toDateString() : null,
            ],
        ]);
    }

    private function generateMapaTtd(Request $request, string $jabkerId, string $docType, string $prefix): JsonResponse
    {
        $user = auth()->user();
        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
        $filename = "{$prefix}_" . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        Barcode::updateOrCreate(
            [
                'id_user'       => $user->id,
                'id_izin'       => $jabkerId,
                'jenis_dokumen' => $docType,
            ],
            [
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'id_role'   => $user->role_id,
            ]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => [
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'tanggal'   => Carbon::now()->toDateString(),
            ],
        ]);
    }
}
