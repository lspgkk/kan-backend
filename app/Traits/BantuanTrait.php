<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait BantuanTrait
{
    private static array $bulanRomwi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
        5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
        9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    private function bulanRomawi(int $month): string
    {
        return self::$bulanRomwi[$month] ?? 'I';
    }

    private function bnspHeaders(): array
    {
        static $authData = null;
        if ($authData === null) {
            $authData = DB::connection('balai')->table('b_n_s_p_auths')->value('x_authorization');
        }
        return [
            'Content-Type' => 'application/json',
            'x-authorization' => $authData,
        ];
    }
}
