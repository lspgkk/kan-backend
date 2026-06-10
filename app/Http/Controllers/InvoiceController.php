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
use App\Models\AsesorBnsp;
use App\Models\AsesiJadwal;
use App\Models\Jadwal;
use App\Models\User;
use App\Models\BuktiAsesmen;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            ->where('status', 30)
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

        $query = Invoice::where('bayar_tahap2', 0);

        if ($request->filled('nama_tuk')) {
            $query->where('nama_tuk', $request->nama_tuk);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('nama_tuk', 'like', "%{$search}%");
            });
        }

        // PAGINATED QUERY
        $invoices = $query
            ->select(['invoice_number', 'nama_tuk', 'inv_link', 'inv_excel', 'input_date', 'total_biaya'])
            ->orderBy('input_date', 'desc')
            ->paginate($perPage);

        // TRANSFORM PAGINATED COLLECTION
        $invoices->getCollection()->transform(function ($invoice) {
            return [
                'no_invoice' => $invoice->invoice_number,
                'tuk'        => $invoice->nama_tuk,
                'nama_tuk'   => $invoice->nama_tuk,
                'link'       => $invoice->inv_link,
                'link_excel' => $invoice->inv_excel,
                'total_biaya' => $invoice->total_biaya,
                'created_at' => $invoice->input_date,
            ];
        });

        // TUK LIST for filter (no pagination needed)
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
            'total_biaya',
            'payment_status',
            'payment_method',
            'midtrans_va_number',
            'midtrans_order_id',
        ]);

        // 🔍 Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
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

        // Transform invoices to include frontend-friendly fields
        $invoices->getCollection()->transform(function ($invoice) {
            $biayaAdmin = 0;
            $statusPembayaran = 'Belum Dibayar';

            // Calculate admin fee for Midtrans payments
            if ($invoice->payment_method === 'midtrans' && $invoice->midtrans_order_id) {
                $midtransTransaction = \App\Models\MidtransTransaction::where('order_id', $invoice->midtrans_order_id)->first();
                if ($midtransTransaction && isset($midtransTransaction->payment_details['midtrans_fee'])) {
                    $biayaAdmin = $midtransTransaction->payment_details['midtrans_fee'];
                }
            }

            // Map payment status
            switch ($invoice->payment_status) {
                case 'paid':
                    $statusPembayaran = 'Sudah Dibayar';
                    break;
                case 'unpaid':
                    $statusPembayaran = 'Belum Dibayar';
                    break;
                case 'pending':
                    $statusPembayaran = 'Dalam Proses';
                    break;
                case 'failed':
                    $statusPembayaran = 'Gagal';
                    break;
                default:
                    $statusPembayaran = $invoice->payment_status ?? 'Belum Dibayar';
            }

            return [
                'no_invoice' => $invoice->invoice_number,
                'tuk' => $invoice->nama_tuk,
                'nama_tuk' => $invoice->nama_tuk, // Add for consistency
                'link' => $invoice->inv_link,
                'link_excel' => $invoice->inv_excel,
                'total_biaya' => $invoice->total_biaya,
                'biaya_admin' => $biayaAdmin,
                'status_pembayaran' => $statusPembayaran,
                'metode_pembayaran' => $invoice->payment_method,
                'created_at' => $invoice->input_date,
                'payment_status' => $invoice->payment_status,
                'midtrans_va_number' => $invoice->midtrans_va_number,
                'midtrans_order_id' => $invoice->midtrans_order_id,
            ];
        });

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
            "metode_pembayaran"   => ['required', 'in:manual,midtrans'],
            "nama_tuk"            => ['required'],
            "rekening"            => ['required_if:metode_pembayaran,manual'],
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
                    $data_instansi = DB::table("mytuk")
                        ->select(['nama_tuk as nama', 'alamat', 'id'])
                        ->where("id", Auth::user()->tuk)
                        ->first();
                } else {
                    $data_instansi = DB::table("mytuk")
                        ->select(['nama_tuk as nama', 'alamat', 'id'])
                        ->where("nama_tuk", $validatedData['nama_tuk'])
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

                    $nama = DB::table("personals")->where("id_izin", trim($id_izin))->value("nama");
                    $hasInvoice = InvoiceDetail::where("id_izin", trim($id_izin))->exists();
                    if (! $nama) {
                        throw new \Exception("Asesi tidak ditemukan: {$id_izin}");
                    }

                    if ($hasInvoice) {
                        throw new \Exception("{$id_izin} sudah memiliki invoice");
                    }

                    $klas_kua = KlasifikasiKualifikasi::select([
                        'id_izin',
                        'jenjang',
                        'tuk',
                        'jabatan_kerja'
                    ])->where('id_izin', $id_izin)->first();

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

                    $jabatan_kerja = DB::table("jabatan_kerjas")
                        ->where("id_jabatan_kerja", $klas_kua->jabatan_kerja)
                        ->value('jabatan_kerja');

                    $nama_tuk = DB::table("mytuk")
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
                        "nama" => $nama,
                        "jabatan_kerja" => $jabatan_kerja,
                        "jenjang" => $klas_kua->jenjang,
                        "harga" => (int)$harga->tahap1,
                        "harga_2" => (int)$harga->tahap2,
                        "nama_tuk" => $nama_tuk,
                        "diskon" => $diskon,
                        "tanggal_uji" => $request->jatuh_tempo,
                    ];
                }

                /* =====================================================
                * 4. INVOICE NUMBER — format: AR-INV/LSP-GKK/tahun/bulan-nourut
                *    Contoh: AR-INV/LSP-GKK/2026/05-0067
                *    Nomor di-reset setiap bulan.
                * ===================================================== */
                $prefix = 'AR-INV/LSP-GKK';
                $year   = date('Y');
                $month  = date('m');

                $latestInv = DB::table('invoices')
                    ->orderByDesc('input_date')
                    ->value('invoice_number');

                if ($latestInv) {
                    $parts = explode('/', $latestInv);
                    $lastSegment = end($parts);
                    $segParts = explode('-', $lastSegment);
                    $lastMonth = $segParts[0] ?? null;
                    $lastNum   = (int) ($segParts[1] ?? 0);
                    $num       = ($lastMonth === $month) ? $lastNum + 1 : 1;
                } else {
                    $num = 1;
                }

                $latest_invno = sprintf('%s/%s/%s-%04d', $prefix, $year, $month, $num);

                // Input excel
                $invoice_sps = IOFactory::load(resource_path("views/template-invoice/invoice_sistem.xlsx"));
                $worksheet = $invoice_sps->getActiveSheet();
                $cell_to_copy = ["B", "C", "D", "E", "F", "G", "H"];

                // Duplicate that shit
                $formatRows = 22;

                for ($i = 0; $i < count($data_asesi_beserta_harga); $i++) {
                    $worksheet->setCellValue('B' . (string)$formatRows + $i, $i + 1);
                    $worksheet->setCellValue('C' . (string)$formatRows + $i, $data_asesi_beserta_harga[$i]['nama']);
                    $worksheet->setCellValue('E' . (string)$formatRows + $i, $data_asesi_beserta_harga[$i]['jabatan_kerja']);
                    $worksheet->setCellValue('F' . (string)$formatRows + $i, $data_asesi_beserta_harga[$i]['jenjang']);
                    $worksheet->setCellValue('G' . (string)$formatRows + $i, (int)$data_asesi_beserta_harga[$i]['harga'] + (int)$data_asesi_beserta_harga[$i]['harga_2']);
                    if($i === count($data_asesi_beserta_harga) - 1) {
                        $worksheet->setCellValue('F' . (string)39 + $i, 'Issued by:');
                        $worksheet->setCellValue('G' . (string)39 + $i, Auth::user()->name);
                    }

                    // Insert the new row
                    if ($i != count($data_asesi_beserta_harga) - 1) {
                        $worksheet->insertNewRowBefore($formatRows + $i + 1, 1);

                        // Copy style from the format row to the new row
                        // Repeat this line for each column you want to copy styles from
                        foreach ($cell_to_copy as $word) {
                            $worksheet->duplicateStyle($worksheet->getStyle($word . $formatRows), $word . (string)$formatRows + $i + 1);
                        }
                    }
                }

                // Instansi
                $worksheet->setCellValue("B11", $data_instansi->nama);
                $worksheet->setCellValue("B12", $data_instansi->alamat);

                // Tuk
                $worksheet->setCellValue("E15", $data_asesi_beserta_harga[0]['nama_tuk']);
                $worksheet->setCellValue("E17", ucwords($validatedData['metode']));

                // Tanggal invoice
                $worksheet->setCellValue("G11", \Carbon\Carbon::now()->setTimezone('Asia/Jakarta')->isoFormat('DD MMMM Y'));

                // Tanggal uji
                $worksheet->setCellValue("E16", \Carbon\Carbon::parse($validatedData['tanggal_uji'])->setTimezone('Asia/Jakarta')->isoFormat('DD MMMM Y'));
                $worksheet->setCellValue("E19", str($tuk_disc->kategori));

                // No Invoice
                $worksheet->setCellValue('G10', $latest_invno);


                // Total
                $worksheet->setCellValue('G' . str($formatRows + count($data_asesi_beserta_harga) + 2), '=SUM(G22:' . 'G' . str((count($data_asesi_beserta_harga) - 1) + $formatRows) . ')');
                $worksheet->setCellValue('G' . str($formatRows + count($data_asesi_beserta_harga) + 3), $total_diskon);

                $total_in_word = $worksheet->getCell('E' . str($formatRows + count($data_asesi_beserta_harga) + 2))->getCalculatedValue();
                $worksheet->getCell('E' . str($formatRows + count($data_asesi_beserta_harga) + 2))->setValue($total_in_word);


                // Save to xlsx
                $writer = IOFactory::createWriter($invoice_sps, "Xlsx");
                // $writer->save(resource_path("views/template-invoice/results.xlsx"));
                ob_start();
                $writer->save('php://output');
                $content = ob_get_contents();
                ob_end_clean();

                $filename_invoice = "invoice_" . Carbon::now()->setTimezone('Asia/Jakarta')->format('Ymd_His') . '.xlsx';
                $result_save = Storage::disk("str_moon")->put("/invoice/" . $filename_invoice, $content);

                /* =====================================================
                * 6. PDF GENERATION (OUTSIDE TRANSACTION)
                * ===================================================== */
                if($result_save) {
                    $pdf = Pdf::loadView('template-invoice.pdf-invoice', [
                        'latest_invno' => $latest_invno,
                        'data_instansi' => $data_instansi,
                        'data_asesi_beserta_harga' => $data_asesi_beserta_harga,
                        'total_harga' => $total_harga,
                        'total_diskon' => $total_diskon,
                        'tanggal_uji' => $validatedData['tanggal_uji'],
                        'metode' => $validatedData['metode'],
                        'kategori_tuk' => $tuk_disc->kategori,
                        'total_word' => $total_in_word,
                        'issued_by' => Auth::user()->name,
                    ]);

                    $pdf_filename = 'inv_' . now()->format('Ymd_His') . '.pdf';
                    Storage::disk("str_moon")->put("/invoice-tuk/" . $pdf_filename, $pdf->output());

                    $invoice_pdf = "https://moon.lspgatensi.id/files/new_gatensi/invoice-tuk/" . $pdf_filename;
                }

                $this->sendInvoiceStatuses($data_asesi_beserta_harga);

                /* =====================================================
                * 5. CREATE INVOICE
                * ===================================================== */
                Invoice::create([
                    'invoice_number' => $latest_invno,
                    'nama'           => $data_instansi->nama,
                    'inv_type'       => $validatedData['metode'] === 'luring' ? 2 : 1,
                    'bulan'          => (int) date('m'),
                    'tahun'          => (int) date('Y'),
                    'nomor'          => $num,
                    'jatuh_tempo'    => 7,
                    'nama_tuk'       => $data_instansi->nama,
                    'class_uji'      => ucwords($validatedData['metode']),
                    'kode_rekening'  => $validatedData['rekening'] ?? null,
                    'keterangan'     => $validatedData['jenis_permohonan'],
                    'total_biaya'    => $total_harga - $total_diskon,
                    'tgl_uji'        => $validatedData['tanggal_uji'],
                    'input_by'       => Auth::user()->email,
                    'inv_link'       => $invoice_pdf,
                    'inv_excel'      => "https://moon.lspgatensi.id/files/new_gatensi/invoice/" . $filename_invoice,
                    'payment_method' => $validatedData['metode_pembayaran'],
                    'payment_status' => $validatedData['metode_pembayaran'] === 'midtrans' ? 'unpaid' : 'paid',
                ]);

                foreach ($data_asesi_beserta_harga as $asesi) {
                    InvoiceDetail::create([
                        'no_invoice' => $latest_invno,
                        'id_izin'    => $asesi['id_izin'],
                        'biaya'      => $asesi['harga'] + $asesi['harga_2'],
                        'ditagihkan' => $asesi['harga'] + $asesi['harga_2'] - $asesi['diskon'],
                        'metode_pembayaran' => $validatedData['metode'], // Use exam method, not payment method
                        'tahap_invoice'    => 'Langsung',
                        'jenis_permohonan' => 1, // Jenis baru, nanti ganti aja id nya
                        'deposit' => $asesi['harga'],
                    ]);
                }

                // Balai/FG auto-confirm: mark invoice as paid
                $hasBalaiFg = Personal::whereIn('id_izin', array_column($data_asesi_beserta_harga, 'id_izin'))
                    ->whereIn('type', ['balai', 'fg'])
                    ->exists();

                if ($hasBalaiFg) {
                    Invoice::where('invoice_number', $latest_invno)->update(['payment_status' => 'paid']);
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

        // Prepare response data
        $responseData = [
            'success' => true,
            'invoice_number' => $latest_invno,
            'invoice_link' => $invoice_pdf,
            'total_biaya' => $total_harga - $total_diskon,
            'payment_method' => $validatedData['metode_pembayaran'],
        ];

        // If Midtrans payment, create transaction and return payment details
        if ($validatedData['metode_pembayaran'] === 'midtrans') {
            try {
                $invoice = Invoice::where('invoice_number', $latest_invno)->first();

                // Import MidtransService
                $midtransService = new \App\Services\MidtransService();

                // Get payment method from request or use null for Midtrans modal
                $paymentMethod = $request->input('midtrans_payment_method');

                // Use Snap (popup with multiple payment options) by default
                try {
                    // Create Snap transaction for popup
                    $snapTransaction = $midtransService->createSnapTransaction($invoice);

                    // Generate order_id for database (same as what we sent to Midtrans)
                    $orderId = $midtransService->generateOrderId($invoice->invoice_number);

                    // Calculate the total amount with admin fee (using default BCA VA fee for storage)
                    $baseAmount = (int) $invoice->total_biaya;
                    $midtransFee = (int) $midtransService->calculateMidtransFee($baseAmount, 'bca_va');
                    $totalAmount = $baseAmount + $midtransFee;

                    // Save Snap transaction details
                    \App\Models\MidtransTransaction::create([
                        'order_id' => $orderId,
                        'invoice_number' => $latest_invno,
                        'transaction_id' => null,
                        'status' => 'pending',
                        'payment_type' => 'snap',
                        'gross_amount' => $totalAmount, // Store total including fees
                        'payment_details' => array_merge($snapTransaction, [
                            'base_amount' => $baseAmount,
                            'midtrans_fee' => $midtransFee,
                            'total_amount' => $totalAmount,
                        ]),
                        'callback_response' => null,
                    ]);

                    // Update invoice with Snap details
                    $invoice->update([
                        'midtrans_order_id' => $orderId,
                        'midtrans_transaction_id' => null,
                        'midtrans_payment_type' => 'snap',
                    ]);

                    $responseData['midtrans'] = [
                        'order_id' => $orderId,
                        'base_amount' => $baseAmount,
                        'midtrans_fee' => $midtransFee,
                        'total_amount' => $totalAmount,
                        'fee_description' => $midtransService->getFeeDescription('bca_va'),
                        'payment_url' => $snapTransaction['redirect_url'],
                        'redirect_url' => $snapTransaction['redirect_url'],
                        'snap_token' => $snapTransaction['token'],
                        'client_key' => $midtransService->getClientKey(),
                        'is_sandbox' => $midtransService->isSandbox(),
                    ];

                } catch (\Exception $snapError) {
                    Log::error('Snap transaction creation failed', [
                        'error' => $snapError->getMessage(),
                        'trace' => $snapError->getTraceAsString()
                    ]);

                    throw new \Exception('Failed to create Midtrans Snap transaction: ' . $snapError->getMessage());
                }

            } catch (\Exception $e) {
                Log::error('Midtrans transaction creation failed', [
                    'invoice_number' => $latest_invno,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create Midtrans transaction: ' . $e->getMessage(),
                    'invoice_number' => $latest_invno,
                ], 500);
            }
        }

        return response()->json($responseData);
    }

    /**
     * Send the appropriate SIKI status sequence and update local DB per asesi type.
     *
     * Reguler : status 30 only (Verifikasi Pembayaran)
     * Balai   : status 30 → 32 → 33 → 31 (auto-confirm)
     * FG      : status 30 → 32 → 33 → 31 (auto-confirm)
     */
    private function sendInvoiceStatuses(array $data_asesi_beserta_harga): void
    {
        $suffixMap = [
            'reguler' => '/',
            'balai'   => '-balai/',
            'fg'      => '-fg/',
        ];

        foreach ($data_asesi_beserta_harga as $asesi) {
            $personal = Personal::where('id_izin', $asesi['id_izin'])->first();
            $type = $personal?->type ?? 'reguler';
            $suffix = $suffixMap[$type] ?? '/';
            $baseUrl = "https://siki.pu.go.id/siki-api/v1/permohonan-skk{$suffix}{$asesi['id_izin']}";

            $headers = [
                'Content-Type' => 'application/json',
                'token' => env('SIKI_TOKEN'),
            ];

            // Step 1: Status 30 (Invoice) — all types
            $this->updateSikiStatus(
                $baseUrl,
                $headers,
                ['kd_status' => '30', 'keterangan' => 'Invoice'],
                30,
                $asesi['id_izin']
            );

            if ($type === 'balai' || $type === 'fg') {
                // Balai/FG auto-confirm: continue to 32 → 33 → 31
                usleep(500000);

                $this->updateSikiStatus(
                    $baseUrl,
                    $headers,
                    ['kd_status' => '32', 'keterangan' => 'Tahap 1'],
                    32,
                    $asesi['id_izin']
                );

                usleep(500000);

                $this->updateSikiStatus(
                    $baseUrl,
                    $headers,
                    ['kd_status' => '33', 'keterangan' => 'BA Ujikom'],
                    33,
                    $asesi['id_izin']
                );

                usleep(500000);

                $this->updateSikiStatus(
                    $baseUrl,
                    $headers,
                    ['kd_status' => '31', 'keterangan' => 'Konfirmasi Pembayaran'],
                    31,
                    $asesi['id_izin']
                );

                Personal::where('id_izin', $asesi['id_izin'])->update(['status' => 31]);
            } else {
                // Reguler: status 30 only
                Personal::where('id_izin', $asesi['id_izin'])->update(['status' => 30]);
            }

            usleep(500000);
        }
    }

    /**
     * Preview discount calculation before invoice creation.
     * Mirrors the discount logic in createInvoice without persisting anything.
     */
    public function previewDiscount(Request $request)
    {
        $validatedData = $request->validate([
            'id_izin_kompeten'   => ['required', 'array'],
            'id_izin_kompeten.*' => ['string'],
            'metode'             => ['required', 'string'],
            'nama_tuk'           => ['required', 'string'],
        ]);

        // Resolve instansi (same logic as createInvoice)
        if (Auth::user()->role === "adm_tuk") {
            $data_instansi = DB::table("mytuk")
                ->select(['nama_tuk as nama', 'alamat', 'id'])
                ->where("id", Auth::user()->tuk)
                ->first();
        } else {
            $data_instansi = DB::table("mytuk")
                ->select(['nama_tuk as nama', 'alamat', 'id'])
                ->where("nama_tuk", $validatedData['nama_tuk'])
                ->first();
        }

        if (!$data_instansi) {
            return response()->json([
                'success' => false,
                'message' => 'Instansi tidak ditemukan',
            ], 404);
        }

        // Discount configuration for the TUK
        $tuk_disc = DB::table("tuk_discounts")
            ->where("id_tuk_lpjk", $data_instansi->id)
            ->first();

        if (!$tuk_disc) {
            return response()->json([
                'success' => false,
                'message' => 'TUK belum rilis diskon',
            ], 404);
        }

        $total_harga  = 0;
        $total_diskon = 0;
        $items        = [];

        foreach ($validatedData['id_izin_kompeten'] as $id_izin) {
            $nama = DB::table("personals")->where("id_izin", trim($id_izin))->value("nama");
            if (!$nama) {
                return response()->json([
                    'success' => false,
                    'message' => "Asesi tidak ditemukan: {$id_izin}",
                ], 404);
            }

            $klas_kua = KlasifikasiKualifikasi::where('id_izin', $id_izin)->first();
            if (!$klas_kua) {
                return response()->json([
                    'success' => false,
                    'message' => "Klasifikasi tidak ditemukan: {$id_izin}",
                ], 404);
            }

            $harga = DB::table("harga_invoice")
                ->where("jenjang", $klas_kua->jenjang)
                ->where("tipe", $validatedData['metode'])
                ->first();

            if (!$harga) {
                return response()->json([
                    'success' => false,
                    'message' => 'Harga tidak ditemukan untuk jenjang ' . $klas_kua->jenjang,
                ], 404);
            }

            $harga_item = (int) $harga->tahap1 + (int) $harga->tahap2;
            $total_harga += $harga_item;

            $jenjang_disc = DB::table("jenjang_discounts")
                ->where("metode", ucwords($validatedData['metode']))
                ->where("jenjang", (int) $klas_kua->jenjang)
                ->first();

            $disc_map = [
                "1"            => $jenjang_disc->kategori_1 ?? 0,
                "2"            => $jenjang_disc->kategori_2 ?? 0,
                "3"            => $jenjang_disc->kategori_3 ?? 0,
                "4"            => $jenjang_disc->kategori_4 ?? 0,
                "Pusat"        => 0,
                "Pemerintahan" => 0,
            ];

            $diskon = $disc_map[$tuk_disc->kategori] ?? 0;
            $total_diskon += $diskon;

            $items[] = [
                'id_izin'    => $id_izin,
                'nama'       => $nama,
                'harga'      => $harga_item,
                'diskon'     => $diskon,
                'ditagihkan' => $harga_item - $diskon,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'total_harga'  => $total_harga,
                'total_diskon' => $total_diskon,
                'total_biaya'  => $total_harga - $total_diskon,
                'items'        => $items,
            ],
        ]);
    }

    /**
     * Update SIKI status helper method
     * TEMPORARILY DISABLED
     */
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
     * Create Midtrans payment for existing unpaid invoice
     */
    public function createMidtransPayment(Request $request)
    {
        try {
            $invoiceNumber = $request->input('invoice_number');
            $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            if ($invoice->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice is already paid'
                ], 400);
            }

            if ($invoice->midtrans_order_id) {
                // Check existing Midtrans transaction
                $existingTransaction = \App\Models\MidtransTransaction::where('order_id', $invoice->midtrans_order_id)->first();

                if ($existingTransaction && $existingTransaction->isPending()) {
                    $midtransService = new \App\Services\MidtransService();

                    $responseData = [
                        'success' => true,
                        'message' => 'Midtrans transaction already exists',
                        'data' => [
                            'order_id' => $existingTransaction->order_id,
                            'amount' => $existingTransaction->gross_amount,
                            'is_sandbox' => $midtransService->isSandbox(),
                        ]
                    ];

                    // If it's a Snap transaction, return the redirect_url
                    if (isset($existingTransaction->payment_details['redirect_url'])) {
                        $responseData['data']['redirect_url'] = $existingTransaction->payment_details['redirect_url'];
                        $responseData['data']['payment_url'] = $existingTransaction->payment_details['redirect_url'];
                    }

                    return response()->json($responseData);
                }
            }

            // Use Snap for better UX (popup with multiple payment options)
            $midtransService = new \App\Services\MidtransService();

            // Create Snap transaction
            $snapTransaction = $midtransService->createSnapTransaction($invoice);

            // Generate order_id for database (same as what we sent to Midtrans)
            $orderId = $midtransService->generateOrderId($invoice->invoice_number);

            // Calculate the total amount with admin fee (using default BCA VA fee for storage)
            $baseAmount = (int) $invoice->total_biaya;
            $midtransFee = (int) $midtransService->calculateMidtransFee($baseAmount, 'bca_va');
            $totalAmount = $baseAmount + $midtransFee;

            // Save Snap transaction details
            $midtransData = [
                'order_id' => $orderId,
                'invoice_number' => $invoiceNumber,
                'transaction_id' => null,
                'status' => 'pending',
                'payment_type' => 'snap',
                'gross_amount' => $totalAmount, // Store total including fees
                'payment_details' => array_merge($snapTransaction, [
                    'base_amount' => $baseAmount,
                    'midtrans_fee' => $midtransFee,
                    'total_amount' => $totalAmount,
                ]),
                'callback_response' => null,
            ];

            \App\Models\MidtransTransaction::create($midtransData);

            // Update invoice with Midtrans details
            $invoice->update([
                'payment_method' => 'midtrans',
                'midtrans_order_id' => $orderId,
                'midtrans_transaction_id' => null,
                'midtrans_payment_type' => 'snap',
            ]);

            $response_data = [
                'success' => true,
                'message' => 'Midtrans payment created successfully',
                'data' => [
                    'order_id' => $orderId,
                    'base_amount' => $baseAmount, // Original invoice amount
                    'midtrans_fee' => $midtransFee, // Fee paid by customer
                    'total_amount' => $totalAmount, // Total customer pays
                    'fee_description' => $midtransService->getFeeDescription('bca_va'),
                    'snap_token' => $snapTransaction['token'],
                    'redirect_url' => $snapTransaction['redirect_url'],
                    'payment_url' => $snapTransaction['redirect_url'],
                    'client_key' => $midtransService->getClientKey(),
                    'is_sandbox' => $midtransService->isSandbox(),
                ]
            ];

            return response()->json($response_data);

        } catch (\Exception $e) {
            Log::error('Failed to create Midtrans payment', [
                'invoice_number' => $invoiceNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Midtrans payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check Midtrans payment status
     */
    public function checkMidtransStatus(Request $request, $invoiceNumber)
    {
        try {
            $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            if (!$invoice->midtrans_order_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Midtrans transaction found for this invoice'
                ], 404);
            }

            $midtransService = new \App\Services\MidtransService();
            $status = $midtransService->checkStatus($invoice->midtrans_order_id);

            $transaction = \App\Models\MidtransTransaction::where('order_id', $invoice->midtrans_order_id)->first();

            if ($transaction) {
                $transaction->update([
                    'status' => $status['transaction_status'] ?? $status['status_code'],
                    'callback_response' => $status,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_number' => $invoiceNumber,
                    'payment_status' => $invoice->payment_status,
                    'transaction_status' => $status['transaction_status'] ?? $status['status_code'],
                    'payment_type' => $status['payment_type'] ?? null,
                    'gross_amount' => $status['gross_amount'] ?? $invoice->total_biaya,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check Midtrans status', [
                'invoice_number' => $invoiceNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check Midtrans status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Midtrans fee breakdown for an invoice
     * Handles both existing invoices and temporary invoice numbers
     */
    public function getMidtransFeeBreakdown(Request $request, $invoiceNumber)
    {
        try {
            $paymentMethod = $request->input('payment_method', 'bca_va');
            $midtransService = new \App\Services\MidtransService();

            // Check if this is a temporary invoice number (starts with TEMP-)
            if (strpos($invoiceNumber, 'TEMP-') === 0) {
                // For temporary invoices, use amount from request if provided
                $amount = $request->input('amount');

                if (!$amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Amount parameter is required for temporary invoice numbers'
                    ], 400);
                }

                $feeBreakdown = $midtransService->getFeeBreakdown((float) $amount, $paymentMethod);
                $supportedMethods = $midtransService->getSupportedPaymentMethods();

                return response()->json([
                    'success' => true,
                    'message' => 'Fee breakdown retrieved successfully (temporary invoice)',
                    'data' => [
                        'invoice_number' => $invoiceNumber,
                        'base_amount' => $feeBreakdown['base_amount'],
                        'midtrans_fee' => $feeBreakdown['midtrans_fee'],
                        'total_amount' => $feeBreakdown['total_amount'],
                        'fee_description' => $feeBreakdown['fee_description'],
                        'payment_method' => $paymentMethod,
                        'supported_methods' => $supportedMethods,
                        'is_temporary' => true,
                    ]
                ]);
            }

            // For existing invoices, look up the invoice
            $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            $feeBreakdown = $midtransService->getFeeBreakdown($invoice->total_biaya, $paymentMethod);
            $supportedMethods = $midtransService->getSupportedPaymentMethods();

            return response()->json([
                'success' => true,
                'message' => 'Fee breakdown retrieved successfully',
                'data' => [
                    'invoice_number' => $invoiceNumber,
                    'base_amount' => $feeBreakdown['base_amount'],
                    'midtrans_fee' => $feeBreakdown['midtrans_fee'],
                    'total_amount' => $feeBreakdown['total_amount'],
                    'fee_description' => $feeBreakdown['fee_description'],
                    'payment_method' => $paymentMethod,
                    'supported_methods' => $supportedMethods,
                    'is_temporary' => false,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get fee breakdown', [
                'invoice_number' => $invoiceNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get fee breakdown: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estimate Midtrans fees without creating invoice
     * Use this for showing fee estimates before invoice creation
     */
    public function estimateMidtransFees(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'amount' => ['required', 'numeric', 'min:0'],
                'payment_method' => ['nullable', 'string'],
            ]);

            $amount = (float) $validatedData['amount'];
            $paymentMethod = $validatedData['payment_method'] ?? 'bca_va';

            $midtransService = new \App\Services\MidtransService();
            $feeBreakdown = $midtransService->getFeeBreakdown($amount, $paymentMethod);

            // Get supported payment methods
            $supportedMethods = $midtransService->getSupportedPaymentMethods();

            return response()->json([
                'success' => true,
                'message' => 'Fee estimation successful',
                'data' => [
                    'base_amount' => $feeBreakdown['base_amount'],
                    'midtrans_fee' => $feeBreakdown['midtrans_fee'],
                    'total_amount' => $feeBreakdown['total_amount'],
                    'fee_description' => $feeBreakdown['fee_description'],
                    'payment_method' => $paymentMethod,
                    'supported_methods' => $supportedMethods,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to estimate fees', [
                'amount' => $request->input('amount'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to estimate fees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unpaid invoices for current user's TUK
     */
    public function getUnpaidInvoices(Request $request)
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 10);

            if ($user->role === 'superadmin') {
                // Can see all unpaid invoices
                $invoices = Invoice::unpaid()
                    ->orderByDesc('input_date')
                    ->paginate($perPage);
            } elseif ($user->role === 'adm_tuk') {
                // Can only see invoices for their TUK
                $tuk = MyTuk::where('id', $user->tuk)->first();

                if (!$tuk) {
                    return response()->json([
                        'success' => false,
                        'message' => 'TUK not found'
                    ], 404);
                }

                $invoices = Invoice::unpaid()
                    ->where('nama_tuk', $tuk->nama_tuk)
                    ->orderByDesc('input_date')
                    ->paginate($perPage);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            // Transform invoices for response
            $invoices->getCollection()->transform(function ($invoice) {
                return [
                    'invoice_number' => $invoice->invoice_number,
                    'nama_tuk' => $invoice->nama_tuk,
                    'total_biaya' => $invoice->total_biaya,
                    'payment_method' => $invoice->payment_method,
                    'payment_status' => $invoice->payment_status,
                    'input_date' => $invoice->input_date,
                    'midtrans_va_number' => $invoice->midtrans_va_number,
                    'midtrans_payment_type' => $invoice->midtrans_payment_type,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Unpaid invoices retrieved successfully',
                'data' => $invoices
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get unpaid invoices', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get unpaid invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    public function confirmInvoice(Request $request)
    {
        try {
            $validatedData = $request->validate([
                "no_invoice" => ['required'],
            ]);

            $headers = [
                'Content-Type' => 'application/json',
                'token' => env('SIKI_TOKEN'),
            ];

            $asesi_invoice = InvoiceDetail::where("no_invoice", $validatedData['no_invoice'])->get();

            // Balai/FG invoices are auto-confirmed during createInvoice — skip manual confirmation
            $idIzinList = $asesi_invoice->pluck('id_izin')->toArray();
            $hasBalaiFg = Personal::whereIn('id_izin', $idIzinList)
                ->whereIn('type', ['balai', 'fg'])
                ->exists();

            if ($hasBalaiFg) {
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice balai/fg sudah dikonfirmasi otomatis saat pembuatan.',
                    'no_invoice' => $validatedData['no_invoice'],
                ], 200);
            }

            DB::beginTransaction();

            // TEMPORARILY DISABLED: SIKI API calls in invoice confirmation
            foreach ($asesi_invoice as $asesi) {
                // Update local status without SIKI API call
                Personal::where('id_izin', $asesi->id_izin)->update(['status' => 31]);

                while (true) {
                    error_log("try again");

                    $api_url = "https://siki.pu.go.id/siki-api/v1/permohonan-skk/" . $asesi->id_izin;

                    $status31 = [
                        "kd_status" => "31",
                        "keterangan" => "Konfirmasi Pembayaran"
                    ];

                    try {
                        $response_post_bnsp = Http::withHeaders($headers)->post($api_url, $status31);
                    } catch (\Exception $e) {
                        error_log("try again");
                        continue;
                    }

                    if ($response_post_bnsp->successful()) {
                        $responseData = $response_post_bnsp->json();

                        if ($responseData['status'] == "success") {
                            Personal::where('id_izin', $asesi->id_izin)
                                ->update(['status' => 31]);
                            break;

                        } elseif ($responseData['status'] == "errors") {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Terjadi kesalahan saat naik ke 31',
                                'detail'  => $responseData['message'] ?? null,
                            ], 422);

                        } else {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => 'Terjadi kesalahan post pencatatan siki',
                                'detail'  => $responseData['message'] ?? null,
                            ], 500);
                        }

                    } else {
                        $errorMessage = $response_post_bnsp->json()['message'] ?? null;

                        if ($errorMessage === 'tidak dapat mengirim kode status 31, kode status saat ini 31') {
                            Personal::where('id_izin', $asesi->id_izin)
                                ->update(['status' => 31]);
                            break;
                        }

                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Server SIKI sedang bermasalah',
                            'detail'  => $errorMessage,
                        ], 503);
                    }
                }

                sleep(2);
            }

            $invoice_current = Invoice::where("invoice_number", $validatedData['no_invoice'])->first();

            $update_invoice = Invoice::where("invoice_number", $validatedData['no_invoice'])->update([
                "bayar_tahap2" => $invoice_current->total_biaya,
                "tgl_tahap2"   => now()->toDateString(),
                "kode_bayar2"  => "-",
                "input_bayar2" => "novi_rara",
            ]);

            if ($update_invoice) {
                DB::commit();

                return response()->json([
                    'success'    => true,
                    'message'    => 'Berhasil confirm invoice',
                    'no_invoice' => $validatedData['no_invoice'],
                ], 200);
            }

            DB::rollBack();
            return response()->json([
                'success'    => false,
                'message'    => 'Gagal update invoice',
                'no_invoice' => $validatedData['no_invoice'],
            ], 500);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Unexpected server error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Midtrans connection (debug endpoint)
     */
    public function testMidtransConnection()
    {
        try {
            $midtransService = new \App\Services\MidtransService();

            // Test basic configuration
            $config = [
                'environment' => $midtransService->isSandbox() ? 'sandbox' : 'production',
                'server_key_prefix' => substr(Config::get('services.midtrans.server_key'), 0, 15) . '...',
                'client_key_prefix' => substr(Config::get('services.midtrans.client_key'), 0, 15) . '...',
            ];

            // Test fee calculation
            $testAmount = 150000;
            $fees = [
                'bca_va' => $midtransService->getFeeBreakdown($testAmount, 'bca_va'),
                'gopay' => $midtransService->getFeeBreakdown($testAmount, 'gopay'),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Midtrans service configuration loaded successfully',
                'data' => [
                    'config' => $config,
                    'test_amount' => $testAmount,
                    'fee_calculations' => $fees,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Midtrans connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Midtrans service test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force payment success (DEBUG/TEST endpoint)
     * This simulates a successful Midtrans payment for testing
     */
    public function forcePaymentSuccess(Request $request, $invoiceNumber)
    {
        try {
            $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            // Update invoice payment status
            $invoice->update([
                'payment_status' => 'paid',
                'midtrans_paid_at' => now(),
                'bayar_tahap2' => $invoice->total_biaya,
                'tgl_tahap2' => now()->toDateString(),
                'kode_bayar2' => 'TEST_PAYMENT',
            ]);

            Log::info('Payment status forced to paid', [
                'invoice_number' => $invoiceNumber,
                'total_biaya' => $invoice->total_biaya,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated to paid',
                'invoice_number' => $invoiceNumber,
                'payment_status' => 'paid',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to force payment success', [
                'invoice_number' => $invoiceNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Laporan Asesor (Excel) berdasarkan bulan & tahun invoice.
     * Mirip seperti getlapasesor pada project balai.
     */
    public function lapAsesor(Request $request)
    {
        $bulan = $request->query('bulan', now()->month);
        $tahun = $request->query('tahun', now()->year);

        // Get invoices for the period
        $invoices = Invoice::where('bulan', (int) $bulan)
            ->where('tahun', (int) $tahun)
            ->get();

        if ($invoices->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Tidak ada invoice untuk bulan {$bulan} tahun {$tahun}",
            ], 404);
        }

        $allData = [];

        foreach ($invoices as $invoice) {
            // Get all asesi for this invoice via invoice_details
            $details = InvoiceDetail::where('no_invoice', $invoice->invoice_number)->get();

            foreach ($details as $detail) {
                $idIzin = $detail->id_izin;

                // Personal data
                $personal = Personal::where('id_izin', $idIzin)->first();
                if (!$personal) continue;

                // Klasifikasi
                $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $idIzin)->first();

                // Jabatan kerja
                $jabatanNama = null;
                $jenjang = null;
                $metode = null;
                $tukNama = null;
                if ($klasifikasi) {
                    $jabatan = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->first();
                    $jabatanNama = $jabatan?->jabatan_kerja;
                    $jenjang = $klasifikasi->jenjang;
                    $metode = $klasifikasi->metode_uji;

                    $tuk = MyTuk::find($klasifikasi->tuk);
                    $tukNama = $tuk?->nama_tuk;
                }

                // AsesiJadwal → get jadwal_id
                $asesiJadwal = AsesiJadwal::where('id_izin', $idIzin)->first();
                $tanggalUji = null;
                $asesor1Noreg = null;
                $asesor2Noreg = null;
                $komtek1 = null;
                $komtek2 = null;
                $komtek3 = null;
                $kompeten = 'BK';

                if ($asesiJadwal) {
                    $jadwal = Jadwal::where('jadwal_id', $asesiJadwal->jadwal_id)->first();
                    if ($jadwal) {
                        $tanggalUji = $jadwal->tanggal_uji;

                        // Asesor
                        $asesor1 = AsesorBnsp::find($jadwal->asesor_id);
                        $asesor2 = AsesorBnsp::find($jadwal->asesor2_id);
                        $asesor1Noreg = $asesor1?->noreg;
                        $asesor2Noreg = $asesor2?->noreg;

                        // Komtek (stored as user_id)
                        $komtek1 = $jadwal->komtek1 ? User::find($jadwal->komtek1)?->name : null;
                        $komtek2 = $jadwal->komtek2 ? User::find($jadwal->komtek2)?->name : null;
                        $komtek3 = $jadwal->komtek3 ? User::find($jadwal->komtek3)?->name : null;
                    }

                    // Kompeten from asesi_jadwals
                    $kompeten = $asesiJadwal->kompeten ?? 'BK';
                }

                // Hadir: check bukti_asesmen exists
                $bukti = BuktiAsesmen::where('id_izin', $idIzin)->first();
                $hadir = $bukti ? 'Hadir' : 'Tidak Hadir';

                $allData[] = [
                    'nama'         => $personal->nama,
                    'jabatan'      => $jabatanNama,
                    'jenjang'      => $jenjang,
                    'metode'       => $metode,
                    'nama_tuk'     => $tukNama,
                    'tanggal_uji'  => $tanggalUji ? Carbon::parse($tanggalUji)->format('Y-m-d') : null,
                    'invoice_no'   => $invoice->invoice_number,
                    'invoice_date' => $invoice->input_date ? Carbon::parse($invoice->input_date)->format('Y-m-d') : null,
                    'asesor1'      => $asesor1Noreg,
                    'asesor2'      => $asesor2Noreg,
                    'komtek1'      => $komtek1,
                    'komtek2'      => $komtek2,
                    'komtek3'      => $komtek3,
                    'kompeten'     => $kompeten,
                    'hadir'        => $hadir,
                ];
            }
        }

        if (empty($allData)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data asesi untuk periode ini',
            ], 404);
        }

        // Generate Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'No', 'Nama', 'Jabatan', 'Jenjang', 'Metode', 'Nama TUK', 'Tanggal Uji',
            'Invoice No', 'Invoice Date', 'Asesor 1', 'Asesor 2',
            'Komtek 1', 'Komtek 2', 'Komtek 3', 'Kompeten', 'Hadir'
        ];

        // Bold header
        $headerStyle = [
            'font' => ['bold' => true],
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

        // Data rows
        $row = 2;
        foreach ($allData as $index => $data) {
            $sheet->fromArray([
                $index + 1,
                $data['nama'],
                $data['jabatan'],
                $data['jenjang'],
                $data['metode'],
                $data['nama_tuk'],
                $data['tanggal_uji'],
                $data['invoice_no'],
                $data['invoice_date'],
                $data['asesor1'],
                $data['asesor2'],
                $data['komtek1'],
                $data['komtek2'],
                $data['komtek3'],
                $data['kompeten'],
                $data['hadir'],
            ], null, "A{$row}");
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = "Laporan_Asesor_{$bulan}_{$tahun}.xlsx";

        // Return as download response
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
