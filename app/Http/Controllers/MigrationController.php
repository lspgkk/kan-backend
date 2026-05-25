<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\DataPencatatan;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\MyTukBnsp;
use App\Models\Pelatihan;
use App\Models\Pendidikan;
use Illuminate\Http\Request;
use App\Models\Personal;
use App\Models\Proyek;
use App\Models\SertifikatSuket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MigrationController extends Controller
{
    public function __construct()
    {
        set_time_limit(12000);
    }


    public function personals()
    {
        $asesi_reguler = DB::connection('fg')
            ->table('personals')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;
                unset($data['id']);

                $data['type'] = 'fg';

                if (!array_key_exists('validby_id', $data) || $data['validby_id'] === null) {
                    $data['validby_id'] = $asesi->validpjby_id ?? null;
                    $data['valid_time'] = $asesi->validpj_time ?? null;
                }

                Log::info('Migrating personal', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                Personal::updateOrCreate(
                    ['id_izin' => $data['id_izin']],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function personalBalai()
    {
        $asesi_reguler = DB::connection('balai')
            ->table('personals')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;
                unset($data['id']);

                $data['type'] = 'balai';

                if (array_key_exists('validpjby_id', $data) || $data['validpjby_id'] !== null) {
                    $data['valid2by_id'] = $asesi->validpjby_id ?? null;
                }

                if ($data['validpj_time'] !== '') {
                    $data['valid2_time'] = $asesi->validpj_time ?? null;
                }

                Log::info('Migrating personal', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                Personal::updateOrCreate(
                    ['id_izin' => $data['id_izin']],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function klasifikasis()
    {
        $asesi_reguler = DB::connection('fg')
            ->table('klasifikasi_kualifikasis')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;

                $exists = Personal::where('id_izin', $data['id_izin'])->exists();

                if (! $exists) {
                    Log::warning('Skipping klasifikasi_kualifikasi, missing personal', [
                        'id_izin' => $data['id_izin']
                    ]);
                    continue;
                }
                unset($data['id']);

                Log::info('Migrating klasifikasi kualifikasi', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                foreach (['created', 'updated'] as $field) {
                    if (
                        isset($data[$field]) &&
                        ($data[$field] === '0000-00-00' || $data[$field] === '0000-00-00 00:00:00')
                    ) {
                        $data[$field] = null;
                    }
                }

                Pendidikan::updateOrCreate(
                    ['id_izin' => $data['id_izin']],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function pendidikans()
    {
        $asesi_reguler = DB::connection('reguler')
            ->table('pendidikans')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;

                $exists = Personal::where('id_izin', $data['id_izin'])->exists();

                if (! $exists) {
                    Log::warning('Skipping klasifikasi_kualifikasi, missing personal', [
                        'id_izin' => $data['id_izin']
                    ]);
                    continue;
                }
                unset($data['id']);

                Log::info('Migrating klasifikasi kualifikasi', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                foreach (['created', 'updated'] as $field) {
                    if (
                        isset($data[$field]) &&
                        ($data[$field] === '0000-00-00' || $data[$field] === '0000-00-00 00:00:00')
                    ) {
                        $data[$field] = null;
                    }
                }

                Pendidikan::updateOrCreate(
                    ['id_izin' => $data['id_izin']],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function proyeks()
    {
        $asesi_reguler = DB::connection('balai')
            ->table('proyeks')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;

                $exists = Personal::where('id_izin', $data['id_izin'])->exists();

                if (! $exists) {
                    Log::warning('Skipping klasifikasi_kualifikasi, missing personal', [
                        'id_izin' => $data['id_izin']
                    ]);
                    continue;
                }
                unset($data['id']);

                Log::info('Migrating klasifikasi kualifikasi', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                foreach (['created', 'updated', 'tanggal_awal', 'tanggal_akhir'] as $field) {
                    if (
                        isset($data[$field]) &&
                        ($data[$field] === '0000-00-00' || $data[$field] === '0000-00-00 00:00:00')
                    ) {
                        $data[$field] = null;
                    }
                }

                Proyek::updateOrCreate(
                    ['id_izin' => $data['id_izin']],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function pelatihans()
    {
        $asesi_reguler = DB::connection('reguler')
            ->table('pelatihans')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;

                $exists = Personal::where('id_izin', $data['id_izin'])->exists();

                if (! $exists) {
                    Log::warning('Skipping klasifikasi_kualifikasi, missing personal', [
                        'id_izin' => $data['id_izin']
                    ]);
                    continue;
                }
                unset($data['id']);

                Log::info('Migrating klasifikasi kualifikasi', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                foreach (['created', 'updated', 'tanggal_awal', 'tanggal_akhir'] as $field) {
                    if (
                        isset($data[$field]) &&
                        ($data[$field] === '0000-00-00' || $data[$field] === '0000-00-00 00:00:00')
                    ) {
                        $data[$field] = null;
                    }
                }

                Pelatihan::updateOrCreate(
                    ['id_izin' => $data['id_izin']],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function sertifikats()
    {
        $asesi_reguler = DB::connection('balai')
            ->table('sertifikat_sukets')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;

                $exists = Personal::where('id_izin', $data['id_izin'])->exists();

                if (! $exists) {
                    Log::warning('Skipping klasifikasi_kualifikasi, missing personal', [
                        'id_izin' => $data['id_izin']
                    ]);
                    continue;
                }
                unset($data['id']);

                Log::info('Migrating klasifikasi kualifikasi', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                foreach (['created', 'updated', 'tanggal_awal', 'tanggal_akhir'] as $field) {
                    if (
                        isset($data[$field]) &&
                        ($data[$field] === '0000-00-00' || $data[$field] === '0000-00-00 00:00:00')
                    ) {
                        $data[$field] = null;
                    }
                }

                SertifikatSuket::updateOrCreate(
                    ['id_izin' => $data['id_izin']],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function pencatatans()
    {
        $asesi_reguler = DB::connection('reguler')
            ->table('data_pencatatans')
            ->get();

        DB::transaction(function () use ($asesi_reguler) {
            foreach ($asesi_reguler as $asesi) {

                $data = (array) $asesi;

                $exists = Personal::where('id_izin', $data['id_izin'])->exists();

                if (! $exists) {
                    Log::warning('Skipping data_pencatatans, missing personal', [
                        'id_izin' => $data['id_izin']
                    ]);
                    continue;
                }

                Log::info('Migrating data pencatatans', [
                    'id_izin' => $data['id_izin'] ?? null
                ]);

                foreach (['created', 'updated', 'tanggal_awal', 'tanggal_akhir'] as $field) {
                    if (
                        isset($data[$field]) &&
                        ($data[$field] === '0000-00-00' || $data[$field] === '0000-00-00 00:00:00')
                    ) {
                        $data[$field] = null;
                    }
                }

                DataPencatatan::updateOrCreate(
                    [
                        'id_izin' => $data['id_izin'],
                        'nomor_blanko_bnsp' => $data['nomor_blangko_bnsp']
                    ],
                    $data
                );
            }
        });

        return response()->json(['message' => 'success']);
    }

    public function jadwals()
    {
        $jadwals = DB::connection('reguler')
            ->table('jadwal_bnsp_table')
            ->get();

        DB::transaction(function () use ($jadwals) {
            foreach ($jadwals as $jadwal) {

                $data = (array) $jadwal;
                unset($data['id']);

                $jadwalId = $data['jadwal_id'] ?? null;
                $idIzin   = $data['id_izin'] ?? null;

                Log::info('Processing jadwal', [
                    'jadwal_id' => $jadwalId,
                    'id_izin'   => $idIzin,
                ]);

                /*
                |--------------------------------------------------------------------------
                | 1️⃣ JADWAL — skip if jadwal_id exists
                |--------------------------------------------------------------------------
                */
                $jadwalModel = Jadwal::where('jadwal_id', $jadwalId)->first();

                if (! $jadwalModel) {

                    // Fix invalid dates
                    foreach (['created', 'updated', 'tanggal_mulai', 'tanggal_selesai'] as $field) {
                        if (
                            isset($data[$field]) &&
                            in_array($data[$field], ['0000-00-00', '0000-00-00 00:00:00'])
                        ) {
                            $data[$field] = null;
                        }
                    }

                    $tuk = MyTukBnsp::select('Nama')
                        ->where('id', $data['tuk_id'])
                        ->first();

                    $data['nama_kegiatan'] =
                        'Kegiatan 1 ' .
                        ($tuk ? $tuk->Nama : '') .
                        ' ' .
                        Carbon::parse($data['created_at'])->format('d/m/Y');

                    // 🚨 IMPORTANT: remove id_izin (belongs to AsesiJadwal)
                    unset($data['id_izin']);

                    $data['tanggal_uji'] = $data['created_at'];

                    $jadwalModel = Jadwal::create($data);

                    // Generate initial documents (SPT Asesor, SPT Komtek, BA Komtek, SK Pelaksanaan Uji)
                    try {
                        (new \App\Http\Controllers\JadwalController())->generateInitialDocuments($jadwalModel->jadwal_id);
                        Log::info('Initial documents generated', ['jadwal_id' => $jadwalId]);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to generate initial documents for jadwal', [
                            'jadwal_id' => $jadwalId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }else {
                    Log::info('Jadwal exists, skipping create', [
                        'jadwal_id' => $jadwalId
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | 2️⃣ ASESI_JADWAL — DO NOT skip by jadwal_id
                |     Only skip if id_izin exists
                |--------------------------------------------------------------------------
                */
                if ($idIzin && AsesiJadwal::where('id_izin', $idIzin)->exists()) {
                    Log::info('AsesiJadwal skipped (id_izin exists)', [
                        'id_izin' => $idIzin
                    ]);
                    continue;
                }

                // Reuse credentials from Personal (set during verification) if available
                $personal = Personal::where('id_izin', $idIzin)->first();
                if ($personal && $personal->account && $personal->password_plain) {
                    $account = $personal->account;
                    $passwordPlain = $personal->password_plain;
                } else {
                    $account = Str::upper(Str::random(8));
                    $passwordPlain = Str::upper(Str::random(8));
                }

                AsesiJadwal::create([
                    'jadwal_id'      => $jadwalId,
                    'id_izin'        => $idIzin,
                    'account'        => $account,
                    'password_plain' => $passwordPlain,
                    'kompeten'       => $data['kompeten']
                ]);

                Log::info('Asesi Jadwal created', [
                    'jadwal_id' => $jadwalId,
                    'id_izin'   => $idIzin
                ]);
            }
        });

        return response()->json(['message' => 'success']);
    }
}
