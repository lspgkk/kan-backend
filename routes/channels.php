<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user has access to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Asesor channel - only the specific asesor can subscribe to their channel
Broadcast::channel('asesor.{asesorId}', function ($user, $asesorId) {
    // Check if the authenticated user is the asesor
    return (int) $user->id === (int) $asesorId;
});

// APL-02 channel - asesor, asesi, and admin can access
Broadcast::channel('apl02.{idIzin}', function ($user, $idIzin) {
    // Check if the user is the asesi (candidate) themselves
    // This assumes the user's personal record has id_izin that matches
    $personal = \App\Models\Personal::where('user_id', $user->id)->first();
    if ($personal && $personal->id_izin === $idIzin) {
        return [
            'role' => 'asesi',
            'id' => $user->id,
            'id_izin' => $idIzin
        ];
    }

    // Check if user is an asesor assigned to this asesi
    $asesiJadwal = \App\Models\AsesiJadwal::where('id_izin', $idIzin)->first();
    if ($asesiJadwal && $asesiJadwal->jadwal) {
        $jadwal = $asesiJadwal->jadwal;

        // Check if user is the assigned asesor or co-asesor
        if ((int) $user->id === (int) $jadwal->asesor_id ||
            (int) $user->id === (int) $jadwal->asesor2_id) {
            return [
                'role' => 'asesor',
                'id' => $user->id,
                'id_izin' => $idIzin
            ];
        }
    }

    // Check if user is admin
    if ($user->role_id === 1) { // Assuming role_id 1 is admin
        return [
            'role' => 'admin',
            'id' => $user->id,
            'id_izin' => $idIzin
        ];
    }

    return false;
});
