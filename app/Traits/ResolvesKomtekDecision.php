<?php

namespace App\Traits;

use App\Models\KomtekRecommendation;

trait ResolvesKomtekDecision
{
    /**
     * Determine K/BK from majority vote of the komtek members.
     * Requires at least 2 votes. Returns 'BK' by default when votes are insufficient.
     */
    public function resolveKomtekDecision(string $id_izin): string
    {
        $votes = KomtekRecommendation::where('id_izin', $id_izin)
            ->pluck('rekomendasi')
            ->toArray();

        if (count($votes) < 2) {
            return 'BK';
        }

        $kCount  = count(array_filter($votes, fn($v) => $v === 'K'));
        $bkCount = count(array_filter($votes, fn($v) => $v === 'BK'));

        return $kCount > $bkCount ? 'K' : 'BK';
    }
}
