<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Models\Personal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\JabatanKerja;
use App\Models\MyTuk;
use App\Models\HargaInvoice;
use App\Models\InvoiceDetail;
use App\Models\Rekening;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function viewInvoiceBaruReguler(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search  = $request->get('search');

        // 1️⃣ Paginate FIRST (with search)
        $asesiPaginated = Personal::query()
            ->where('status', 10)
            ->where('type', 'reguler')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('id_izin', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->select(DB::raw(1))
                            ->from('klasifikasi_kualifikasis as kk')
                            ->join('mytuk as t', 't.id', '=', 'kk.tuk')
                            ->whereColumn('kk.id_izin', 'personals.id_izin')
                            ->where('t.nama_tuk', 'like', "%{$search}%");
                    });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // 2️⃣ Transform (UNCHANGED)
        $asesiPaginated->getCollection()->transform(function ($asesi) {

            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->first();
            if (!$klasifikasi) return null;

            $tuk = MyTuk::where('id', $klasifikasi->tuk)->first();

            $jabker = JabatanKerja::where(
                'id_jabatan_kerja',
                $klasifikasi->jabatan_kerja
            )->first();

            $harga = HargaInvoice::where('jenjang', $klasifikasi->jenjang)
                ->where('tipe', 'Luring')
                ->first();

            return [
                'nama'          => $asesi->nama,
                'nik'           => $asesi->nik,
                'id_izin'       => $asesi->id_izin,
                'jabatan_kerja' => $jabker->jabatan_kerja ?? '-',
                'jenjang'       => $klasifikasi->jenjang,
                'tuk'           => $tuk->nama_tuk ?? '-',
                'harga_bayar'   => ($harga->tahap1 ?? 0) + ($harga->tahap2 ?? 0),
            ];
        });

        // 3️⃣ Remove nulls
        $asesiPaginated->setCollection(
            $asesiPaginated->getCollection()->filter()->values()
        );

        return response()->json([
            'message' => 'Invoice Reguler retrieved successfully!',
            'data' => $asesiPaginated
        ]);
    }

    public function viewInvoiceBaruBalai(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search  = $request->get('search');

        // 1️⃣ Paginate FIRST (with search)
        $asesiPaginated = Personal::query()
            ->where('status', 10)
            ->where('type', 'balai')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('id_izin', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->select(DB::raw(1))
                            ->from('klasifikasi_kualifikasis as kk')
                            ->join('mytuk as t', 't.id', '=', 'kk.tuk')
                            ->whereColumn('kk.id_izin', 'personals.id_izin')
                            ->where('t.nama_tuk', 'like', "%{$search}%");
                    });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // 2️⃣ Transform (UNCHANGED)
        $asesiPaginated->getCollection()->transform(function ($asesi) {

            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->first();
            if (!$klasifikasi) return null;

            $tuk = MyTuk::where('id', $klasifikasi->tuk)->first();

            $jabker = JabatanKerja::where(
                'id_jabatan_kerja',
                $klasifikasi->jabatan_kerja
            )->first();

            $harga = HargaInvoice::where('jenjang', $klasifikasi->jenjang)
                ->where('tipe', 'Luring')
                ->first();

            return [
                'nama'          => $asesi->nama,
                'nik'           => $asesi->nik,
                'id_izin'       => $asesi->id_izin,
                'jabatan_kerja' => $jabker->jabatan_kerja ?? '-',
                'jenjang'       => $klasifikasi->jenjang,
                'tuk'           => $tuk->nama_tuk ?? '-',
                'harga_bayar'   => ($harga->tahap1 ?? 0) + ($harga->tahap2 ?? 0),
            ];
        });

        // 3️⃣ Remove nulls
        $asesiPaginated->setCollection(
            $asesiPaginated->getCollection()->filter()->values()
        );

        return response()->json([
            'message' => 'Invoice Reguler retrieved successfully!',
            'data' => $asesiPaginated
        ]);
    }

    public function viewInvoiceBaruFg(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search  = $request->get('search');

        // 1️⃣ Paginate FIRST (with search)
        $asesiPaginated = Personal::query()
            ->where('status', 10)
            ->where('type', 'fg')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('id_izin', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search) {
                        $sub->select(DB::raw(1))
                            ->from('klasifikasi_kualifikasis as kk')
                            ->join('mytuk as t', 't.id', '=', 'kk.tuk')
                            ->whereColumn('kk.id_izin', 'personals.id_izin')
                            ->where('t.nama_tuk', 'like', "%{$search}%");
                    });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // 2️⃣ Transform (UNCHANGED)
        $asesiPaginated->getCollection()->transform(function ($asesi) {

            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->first();
            if (!$klasifikasi) return null;

            $tuk = MyTuk::where('id', $klasifikasi->tuk)->first();

            $jabker = JabatanKerja::where(
                'id_jabatan_kerja',
                $klasifikasi->jabatan_kerja
            )->first();

            $harga = HargaInvoice::where('jenjang', $klasifikasi->jenjang)
                ->where('tipe', 'Luring')
                ->first();

            return [
                'nama'          => $asesi->nama,
                'nik'           => $asesi->nik,
                'id_izin'       => $asesi->id_izin,
                'jabatan_kerja' => $jabker->jabatan_kerja ?? '-',
                'jenjang'       => $klasifikasi->jenjang,
                'tuk'           => $tuk->nama_tuk ?? '-',
                'harga_bayar'   => ($harga->tahap1 ?? 0) + ($harga->tahap2 ?? 0),
            ];
        });

        // 3️⃣ Remove nulls
        $asesiPaginated->setCollection(
            $asesiPaginated->getCollection()->filter()->values()
        );

        return response()->json([
            'message' => 'Invoice Reguler retrieved successfully!',
            'data' => $asesiPaginated
        ]);
    }

    public function rekening()
    {
        $rekening = Rekening::get();

        return response()->json([
            'message' => 'Rekening TUK retrieved successfully!',
            'data' => $rekening
        ]);
    }

    public function viewKonfirmasi(Request $request)
    {
        // default per page = 10
        $perPage = $request->integer('per_page', 10);

        $belum_konfirm = Invoice::where('bayar_tahap2', 0);

        // PAGINATED QUERY
        $invoices = $belum_konfirm
            ->select(['invoice_number', 'nama_tuk', 'inv_link', 'input_date', 'total_biaya'])
            ->orderBy('input_date', 'desc')
            ->paginate($perPage);

        // TRANSFORM PAGINATED COLLECTION
        $invoices->getCollection()->transform(function ($invoice) {
            return [
                'no_invoice' => $invoice->invoice_number,
                'tuk'        => $invoice->nama_tuk,
                'link'       => $invoice->inv_link,
                'total_biaya' => $invoice->total_biaya,
                'created_at' => $invoice->input_date,
            ];
        });

        // FILTERED TUK LIST (no pagination needed)
        $tuk_filtered = Invoice::where('bayar_tahap2', 0)
            ->select('nama_tuk')
            ->distinct()
            ->get()
            ->map(fn ($tuk) => [
                'nama_tuk' => $tuk->nama_tuk
            ]);

        return response()->json([
            'message' => 'Invoices retrieved successfully!',
            'data' => [
                'invoices' => $invoices,
                'tuk_filtered' => $tuk_filtered
            ]
        ]);
    }

    public function history(Request $request)
    {
        $query = Invoice::select([
            'invoice_number',
            'nama_tuk',
            'inv_link',
            'inv_excel',
            'input_date',
            'bulan',
            'tahun',
            'total_biaya'
        ]);

        // 🔍 Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('no_invoice', 'like', "%{$search}%")
                ->orWhere('nama_tuk', 'like', "%{$search}%");
            });
        }

        // 📅 Tahun
        if ($request->filled('tahun')) {
            $query->where('tahun', $request->tahun);
        }

        // 📅 Bulan
        if ($request->filled('bulan')) {
            $query->where('bulan', $request->bulan);
        }

        // 🏢 TUK
        if ($request->filled('nama_tuk')) {
            $query->where('nama_tuk', $request->nama_tuk);
        }

        $invoices = $query
            ->orderByDesc('input_date')
            ->paginate($request->get('per_page', 10));

        // optional filter list
        $tukFiltered = Invoice::select('nama_tuk')
            ->distinct()
            ->whereNotNull('nama_tuk')
            ->get()
            ->map(fn ($tuk) => [
                'nama_tuk' => $tuk->nama_tuk
            ]);

        return response()->json([
            'message' => 'History invoice berhasil dimuat',
            'data' => [
                'invoices' => $invoices,   // ✅ SAME KEY
                'tuk_filtered' => $tukFiltered
            ]
        ]);
    }

    public function createInvoice(Request $request)
    {
        $validatedData = $request->validate([
            "id_izin_kompeten"    => ['required', 'array'],
            "jenis_permohonan"    => ['required'],
            "metode"              => ['required'],
            "metode_pembayaran"   => ['required'],
            "nama_tuk"            => ['required'],
            "rekening"            => ['required'],
            "jatuh_tempo"         => ['required'],
            "tanggal_uji"         => ['required'],
        ]);

        $maxRetries = 3;
        $attempt = 0;

        /* =====================================================
        * DATA HOLDERS
        * ===================================================== */
        $data_asesi_beserta_harga = [];
        $total_harga  = 0;
        $total_diskon = 0;
        $latest_invno = null;
        $invoice      = null;
        $invoice_pdf  = null;

        while ($attempt < $maxRetries) {
            DB::beginTransaction();

            try {
                /* =====================================================
                * 1. INSTANSI
                * ===================================================== */
                if (Auth::user()->role === "adm_tuk") {
                    $data_instansi = DB::connection("mygatensi_2")
                        ->table("mytuk")
                        ->select(['nama_tuk as nama', 'alamat', 'id'])
                        ->where("id", Auth::user()->tuk)
                        ->first();
                } else {
                    $data_instansi = DB::connection("mygatensi_2")
                        ->table("mytuk")
                        ->select(['nama_tuk as nama', 'alamat', 'id'])
                        ->where("id", $validatedData['nama_tuk'])
                        ->first();
                }

                if (! $data_instansi) {
                    throw new \Exception("Instansi tidak ditemukan");
                }

                /* =====================================================
                * 2. DISCOUNT CONFIG
                * ===================================================== */
                $tuk_disc = DB::table("tuk_discounts")
                    ->where("id_tuk_lpjk", $data_instansi->id)
                    ->first();

                if (! $tuk_disc) {
                    throw new \Exception("TUK belum rilis diskon");
                }

                /* =====================================================
                * 3. LOOP ASESI
                * ===================================================== */
                foreach ($validatedData['id_izin_kompeten'] as $id_izin) {

                    $personal = DB::connection("mygatensi_2")
                        ->table("mystatuspermohonan")
                        ->select(['ID_Ijin as id_izin', 'NamaAsesi as nama', 'vertgluji as tanggal_uji', 'invno'])
                        ->where("ID_Ijin", trim($id_izin))
                        ->first();

                    if (! $personal) {
                        throw new \Exception("Asesi tidak ditemukan: {$id_izin}");
                    }

                    if (! empty($personal->invno)) {
                        throw new \Exception("{$id_izin} sudah memiliki invoice");
                    }

                    $klas_kua = KlasifikasiKualifikasi::where('id_izin', $id_izin)->first()
                        ?? DB::connection("mygatensi_2")
                            ->table("myklasifikasikualifikasi")
                            ->select([
                                'ID_Ijinklasifikasikualifikasi as id_izin',
                                'jenjang',
                                'tuk',
                                'jabatan_kerja'
                            ])
                            ->where("ID_Ijinklasifikasikualifikasi", trim($id_izin))
                            ->first();

                    if (! $klas_kua) {
                        throw new \Exception("Klasifikasi tidak ditemukan: {$id_izin}");
                    }

                    $harga = DB::table("harga_invoice")
                        ->where("jenjang", $klas_kua->jenjang)
                        ->where("tipe", $validatedData['metode'])
                        ->first();

                    if (! $harga) {
                        throw new \Exception("Harga tidak ditemukan");
                    }

                    $jabatan_kerja = DB::connection("mygatensi_2")
                        ->table("myjabatankerja")
                        ->where("id_jabatan_kerja", $klas_kua->jabatan_kerja)
                        ->value('jabatan_kerja');

                    $nama_tuk = DB::connection("mygatensi_2")
                        ->table("mytuk")
                        ->where("id", $klas_kua->tuk)
                        ->value('nama_tuk');

                    $total_harga += (int)$harga->tahap1 + (int)$harga->tahap2;

                    $jenjang_disc = DB::table("jenjang_discounts")
                        ->where("metode", ucwords($validatedData['metode']))
                        ->where("jenjang", (int)$klas_kua->jenjang)
                        ->first();

                    $disc_map = [
                        "1" => $jenjang_disc->kategori_1 ?? 0,
                        "2" => $jenjang_disc->kategori_2 ?? 0,
                        "3" => $jenjang_disc->kategori_3 ?? 0,
                        "4" => $jenjang_disc->kategori_4 ?? 0,
                        "Pusat" => 0,
                        "Pemerintahan" => 0,
                    ];

                    $diskon = $disc_map[$tuk_disc->kategori] ?? 0;
                    $total_diskon += $diskon;

                    $data_asesi_beserta_harga[] = [
                        "id_izin" => $id_izin,
                        "nama" => $personal->nama,
                        "jabatan_kerja" => $jabatan_kerja,
                        "jenjang" => $klas_kua->jenjang,
                        "harga" => (int)$harga->tahap1,
                        "harga_2" => (int)$harga->tahap2,
                        "nama_tuk" => $nama_tuk,
                        "diskon" => $diskon,
                        "tanggal_uji" => $personal->tanggal_uji,
                    ];
                }

                /* =====================================================
                * 4. INVOICE NUMBER
                * ===================================================== */
                $latest_inv = DB::connection("mygatensi_2")
                    ->table("myinvoice")
                    ->orderByDesc('InputDate')
                    ->value('InvNo');

                [$a,$b,$c,$d] = explode("/", $latest_inv);
                $c = date('Y');
                [$month,$num] = explode("-", $d);

                $num = ($month == date('m')) ? $num + 1 : 1;
                $month = date('m');

                $latest_invno = implode("/", [$a,$b,$c,$month . '-' . str_pad($num, 4, '0', STR_PAD_LEFT)]);

                /* =====================================================
                * 5. CREATE INVOICE
                * ===================================================== */
                $invoice = Invoice::create([
                    'invoice_number' => $latest_invno,
                    'nama'           => $data_instansi->nama,
                    'inv_type'       => $validatedData['metode'] === 'luring' ? 2 : 1,
                    'bulan'          => (int) date('m'),
                    'tahun'          => (int) date('Y'),
                    'nomor'          => $num,
                    'jatuh_tempo'    => (int) $validatedData['jatuh_tempo'],
                    'nama_tuk'       => $data_instansi->nama,
                    'class_uji'      => ucwords($validatedData['metode']),
                    'kode_rekening'  => $validatedData['rekening'],
                    'keterangan'     => $validatedData['jenis_permohonan'],
                    'total_biaya'    => $total_harga - $total_diskon,
                    'tgl_uji'        => $validatedData['tanggal_uji'],
                    'input_by'       => Auth::user()->email,
                ]);

                foreach ($data_asesi_beserta_harga as $asesi) {
                    InvoiceDetail::create([
                        'no_invoice' => $latest_invno,
                        'id_izin'    => $asesi['id_izin'],
                        'biaya'      => $asesi['harga'] + $asesi['harga_2'],
                        'ditagihkan' => $asesi['harga'] + $asesi['harga_2'] - $asesi['diskon'],
                    ]);
                }

                DB::commit();
                break;

            } catch (\Throwable $e) {
                DB::rollBack();
                if (++$attempt >= $maxRetries) {
                    return response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ], 500);
                }
            }
        }

        /* =====================================================
        * 6. PDF GENERATION (OUTSIDE TRANSACTION)
        * ===================================================== */
        $pdf = Pdf::loadView('invoice.pdf-invoice', [
            'latest_invno' => $latest_invno,
            'data_instansi' => $data_instansi,
            'data_asesi_beserta_harga' => $data_asesi_beserta_harga,
            'total_harga' => $total_harga,
            'total_diskon' => $total_diskon,
        ]);

        $pdf_filename = 'inv_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk("str_moon")->put("/invoice-tuk/" . $pdf_filename, $pdf->output());

        $invoice_pdf = "https://moon.lspgatensi.id/files/invoice-tuk/" . $pdf_filename;
        $invoice->update(['inv_link' => $invoice_pdf]);

        /* =====================================================
        * 7. SIKI + PERSONAL UPDATE
        * ===================================================== */
        foreach ($data_asesi_beserta_harga as $asesi) {
            $this->updateSikiStatus(
                "https://siki.pu.go.id/siki-api/v1/permohonan-skk/" . $asesi['id_izin'],
                [
                    'Content-Type' => 'application/json',
                    'token' => env('SIKI_TOKEN'),
                ],
                ['kd_status' => '30', 'keterangan' => 'Invoice'],
                30,
                $asesi['id_izin']
            );

            Personal::where('id_izin', $asesi['id_izin'])->update(['status' => 30]);
            usleep(500000);
        }

        return response()->json([
            'success' => true,
            'invoice_number' => $latest_invno,
            'invoice_link' => $invoice_pdf,
            'total_biaya' => $total_harga - $total_diskon,
        ]);
    }

    private function updateSikiStatus($api_url, $headers, $status_payload, $status_code, $id_izin)
    {
        $maxRetries = 4;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $response = Http::withHeaders($headers)->post($api_url, $status_payload);

                if ($response->successful()) {
                    $responseData = $response->json();
                    if ($responseData['status'] == "success") {
                        Log::info("Successfully updated to status $status_code for ID_Izin: $id_izin");
                        return;
                    } else if ($responseData['status'] == "errors") {
                        throw new \Exception("Failed to update status $status_code: " . $responseData['message']);
                    }
                } else {
                    $responseData = $response->json();
                    // Handle specific messages that indicate the status is already set
                    if ($response->status() == 403) {
                        Log::info("Status $status_code is already set for ID_Izin: $id_izin. Skipping to the next status.");
                        return;
                    } else {
                        throw new \Exception("API Error: " . $responseData['message']);
                    }
                }
            } catch (\Exception $e) {
                $attempt++;
                Log::warning("Attempt $attempt: Failed to update status $status_code for ID_Izin: $id_izin. Error: " . $e->getMessage());

                if ($attempt >= $maxRetries) {
                    throw new \Exception("Failed to update status $status_code after $maxRetries attempts for ID_Izin: $id_izin");
                }

                // Optional: Wait before retrying
                sleep(1);
            }
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        //
    }
}
