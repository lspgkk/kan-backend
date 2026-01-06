<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Models\Personal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\JabatanKerja;
use App\Models\MyTuk;
use App\Models\HargaInvoice;
use App\Models\Rekening;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function viewInvoiceBaruReguler(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        // 1️⃣ Paginate FIRST
        $asesiPaginated = Personal::where('status', 10)->where('type', 'reguler')->paginate($perPage);

        // 2️⃣ Transform the paginated items
        $asesiPaginated->getCollection()->transform(function ($asesi) {

            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->first();

            if (!$klasifikasi) {
                return null;
            }

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

        // 3️⃣ Remove nulls (important)
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
    public function viewInvoiceBaruBalai(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        // 1️⃣ Paginate FIRST
        $asesiPaginated = Personal::where('status', 10)->where('type', 'fg')->paginate($perPage);

        // 2️⃣ Transform the paginated items
        $asesiPaginated->getCollection()->transform(function ($asesi) {

            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->first();

            if (!$klasifikasi) {
                return null;
            }

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

        // 3️⃣ Remove nulls (important)
        $asesiPaginated->setCollection(
            $asesiPaginated->getCollection()->filter()->values()
        );

        return response()->json([
            'message' => 'Invoice Balai retrieved successfully!',
            'data' => $asesiPaginated
        ]);
    }

    public function viewInvoiceBaruFg(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        // 1️⃣ Paginate FIRST
        $asesiPaginated = Personal::where('status', 10)->where('type', 'fg')->paginate($perPage);

        // 2️⃣ Transform the paginated items
        $asesiPaginated->getCollection()->transform(function ($asesi) {

            $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->first();

            if (!$klasifikasi) {
                return null;
            }

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

        // 3️⃣ Remove nulls (important)
        $asesiPaginated->setCollection(
            $asesiPaginated->getCollection()->filter()->values()
        );

        return response()->json([
            'message' => 'Invoice FG retrieved successfully!',
            'data' => $asesiPaginated
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
        $maxRetries = 3;
        $attempt = 0;
        $personalUpdates = [];
        $invoiceData = [];
        $filename_invoice = null;

        while ($attempt < $maxRetries) {
            try {
                $validatedData = $request->validate([
                    "id_izin_kompeten" => ['required'],
                    "metode" => ['required'],
                    "nama_instansi" => ['required'],
                    "tanggal_uji" => ['required']
                ]);

                // Get data tuk for instansi
                if (Auth::user()->role == "adm_tuk") {
                    $data_instansi = MyTuk::select(['nama_tuk as nama', 'alamat', 'id'])->where("id", Auth::user()->tuk)->first();
                } else {
                    $data_instansi = MyTuk::select(['nama_tuk as nama', 'alamat', 'id'])->where("id", $validatedData['nama_instansi'])->first();
                }
                $data_asesi_beserta_harga = [];
                $total_harga = 0;
                $total_diskon = 0;

                $tuk_disc = DB::table("tuk_discounts")->select(['kategori'])->where("id_tuk_lpjk", $data_instansi->id)->first();

                foreach ($validatedData['id_izin_kompeten'] as $id_izin) {
                    $personal = DB::connection("mygatensi_2")->table("mystatuspermohonan")->select(['ID_Ijin as id_izin', 'NamaAsesi as nama', 'vertgluji as tanggal_uji'])->where("ID_Ijin", trim($id_izin))->first();
                    if (isset($personal->invno)) {
                        return back()->with("error", $personal->id_izin . " sudah create invoice!");
                    }
                    $klas_kua = KlasifikasiKualifikasi::where('id_izin', $id_izin)->first();
                    if(!isset($klas_kua)) {
                        $klas_kua = DB::connection("mygatensi_2")->table("myklasifikasikualifikasi")->select(['ID_Ijinklasifikasikualifikasi as id_izin', 'jenjang as jenjang', 'tuk as tuk', 'jabatan_kerja as jabatan_kerja'])->where("ID_Ijinklasifikasikualifikasi", trim($id_izin))->first();
                    }
                    $get_harga = DB::table("harga_invoice")->select(['tahap1', 'tahap2', 'tipe', 'jenjang'])->where("jenjang", "$klas_kua->jenjang")->where("tipe", $validatedData['metode'])->first();
                    $jabatan_kerja = DB::connection("mygatensi_2")->table("myjabatankerja")->select(['jabatan_kerja'])->where("id_jabatan_kerja", $klas_kua->jabatan_kerja)->first();
                    if (!$jabatan_kerja) {
                        throw new \Exception("Jabatan kerja not found for ID: " . $klas_kua->jabatan_kerja);
                    }
                    $total_harga += (int)$get_harga->tahap1 + (int)$get_harga->tahap2;

                    $nama_tuk = DB::connection("mygatensi_2")->table("mytuk")->select(['nama_tuk'])->where("id", $klas_kua->tuk)->first();
                    if (!$nama_tuk) {
                        throw new \Exception("TUK not found for ID: " . $klas_kua->tuk);
                    }

                    if ($tuk_disc) {
                        $jenjang_disc = DB::table("jenjang_discounts")->where("metode", "Luring")->where("jenjang", (int)$klas_kua->jenjang)->first();
                        $disc_full = [
                            "1" => $jenjang_disc->kategori_1,
                            "2" => $jenjang_disc->kategori_2,
                            "3" => $jenjang_disc->kategori_3,
                            "4" => $jenjang_disc->kategori_4,
                            "Pusat" => 0,
                            "Pemerintahan" => 0
                        ];
                        $dapat_disc = $disc_full[$tuk_disc->kategori];
                        $total_diskon = $total_diskon + $dapat_disc;
                    } else {
                        return back()->with("error", $nama_tuk->nama_tuk . " belum rilis!");
                    }

                    array_push($data_asesi_beserta_harga, [
                        "id_izin" => $id_izin,
                        "nama" => $personal->nama,
                        "jabatan_kerja" => $jabatan_kerja->jabatan_kerja,
                        "jenjang" => $klas_kua->jenjang,
                        "harga" => $get_harga->tahap1,
                        "harga_2" => $get_harga->tahap2,
                        "nama_tuk" => $nama_tuk->nama_tuk,
                        "id_tuk" => $klas_kua->tuk,
                        "diskon" => $dapat_disc,
                        "rencana_tanggal_uji" => $personal->tanggal_uji
                    ]);
                }

                $latest_invno = DB::connection("mygatensi_2")->table("myinvoice")->select(["invoice_number"])->orderBy('input_date', 'desc')->first();
                $latest_invno = explode("/", $latest_invno->invoice_number);
                $latest_invno[2] = date("Y");

                $number_invno = explode("-", $latest_invno[3]);
                if ($number_invno[0] == str(date("m"))) {
                    $number_invno[1] = (int)$number_invno[1] + 1;
                    $copy_number = (int)$number_invno[1];
                    $number_invno[1] = str_pad($number_invno[1], 4, '0', STR_PAD_LEFT);
                } else {
                    $number_invno[0] = str(date("m"));
                    $copy_number = 1;
                    $number_invno[1] = str_pad("1", 4, '0', STR_PAD_LEFT);
                }
                $latest_invno[3] = implode("-", $number_invno);
                $latest_invno = implode("/", $latest_invno);

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

                $filename_invoice = "invoice_" . \Carbon\Carbon::now()->setTimezone('Asia/Jakarta')->toDateTimeString() .  '.xlsx';
                $result_save = Storage::disk("sertif_ftp")->put("/baru/invoice/" . $filename_invoice, $content);

                $response = Http::withHeaders([
                    "Content-Type" => "application/json",
                    "token" => "3703A37836D0D2B451CAABEDD969A26A15FCFBB65C75070ECB4142611FA5F6A070063E18C762D13AED801B2B174A9BAA97F07EDE9A9587F4C5DB3A39CC930744"
                ])->post("http://147.139.174.124:6183/convert", [
                    "file" => "https://lspgatensi.id/files/baru/invoice/" . $filename_invoice
                ]);
                if ($response->ok()) {
                    $result_json = $response->json();
                    $invoice_pdf = $result_json['link'];
                } else {
                    dd("Terjadi masalah: converter xls_pdf sedang bermasalah");
                    abort(500);
                }

                foreach ($data_asesi_beserta_harga as $asesi) {
                    $api_url = "https://siki.pu.go.id/siki-api/v1/permohonan-skk/" . trim($asesi['id_izin']);
                    $headers = [
                        'Content-Type' => 'application/json',
                        'token' => env('SIKI_TOKEN')
                    ];

                    $status30 = ["kd_status" => "30", "keterangan" => "Invoice"];

                    $this->updateSikiStatus($api_url, $headers, $status30, 30, $asesi['id_izin']);

                    // If all SIKI updates are successful, prepare data for personal table update and invoice creation
                    $personalUpdates[] = [
                        'id_izin' => $asesi['id_izin'],
                        'status' => 30,
                        'harga_2' => $asesi['harga_2'],
                        'harga' => $asesi['harga']
                    ];

                    usleep(500000); // 0.5 seconds delay
                }

                foreach ($personalUpdates as $personalUpdate) {
                    DB::connection("mygatensi_2")->table("mystatuspermohonan")->where("ID_Ijin", $personalUpdate['id_izin'])->update([
                        "invby" => Auth::user()->email,
                        "invstatus" => $personalUpdate['status'],
                        "invno" => $latest_invno,
                        "InvBiaya" => $personalUpdate['harga_2'],
                        "InvBayar" => $personalUpdate['harga'],
                        "invdate" => date("Y-m-d"),
                        "Invbayarby" => "novi_rara",
                        "Invbayarstatus" => "30",
                        "Invbayardate" => now()
                    ]);

                    Personal::where('id_izin', $personalUpdate['id_izin'])->update([
                        'status' => 30
                    ]);
                }

                DB::connection("mygatensi_2")->table("myinvoice")->insert([
                    "ID_Ijin" => $validatedData['metode'] == "luring" ? "TUK Mandiri" : "BJKW Kolektif",
                    "Nama" => $data_instansi->nama,
                    "InvType" => $validatedData['metode'] == "luring" ? 2 : 1,
                    "bulan" => (int)date("m"),
                    "tahun" => (int)date("Y"),
                    "nomor" => $copy_number,
                    "JatuhTempo" => 7,
                    "invoice_number" => $latest_invno,
                    "nama_tuk" => $data_instansi->nama,
                    "ClassUji" => $validatedData['metode'] == "luring" ? "Luring" : "On_Site",
                    "KodeRekening" => "000",
                    "Keterangan" => $validatedData['metode'] == "luring" ? "Biaya Sertifikasi TUK" : "Biaya Sertifikasi Kolektif",
                    "TotalBiaya" => $total_harga - $total_diskon,
                    "BayarTahap1" => 0,
                    "BayarTahap2" => 0,
                    "RefundBayar" => 0,
                    "TglUji" => $data_asesi_beserta_harga[0]['rencana_tanggal_uji'],
                    "StatusJadwal" => 0,
                    "inv_link" => $invoice_pdf,
                    "InputBy" => Auth::user()->email,
                    "SinServer" => "0"
                ]);

                DB::table("file_invoices")->insert([
                    "no_invoice" => $latest_invno,
                    "link" => "https://lspgatensi.id/files/baru/invoice/" . $filename_invoice
                ]);

                if (Auth::user()->role == "adm_tuk") {
                    return redirect("/invoice/laporan")->with('success', 'Berhasil membuat laporan, silahkan klik link berikut download file pdf <a class="underline" download href="' . $invoice_pdf . '">' . $invoice_pdf . '</a>');
                } else {
                    return redirect("/invoice-admin/laporan")->with('success', 'Berhasil membuat laporan, silahkan klik link berikut download file pdf <a class="underline" download href="' . $invoice_pdf . '">' . $invoice_pdf . '</a>');
                }

            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    dd($e);
                    return back()->with("error", "Terjadi kesalahan: " . $e->getMessage())->withInput();
                }
            }
        }
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
