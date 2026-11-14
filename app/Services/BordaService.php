<?php

namespace App\Services;

use App\Enums\TieBreakRule;
use InvalidArgumentException;

/**
 * Borda Count dengan pemecah seri D05 (Bab 5.3).
 *
 * Poin posisi ke-k dari m alternatif = m − k. Bila total poin sama:
 *   1) V TOPSIS kelompok lebih besar menang;
 *   2) bila V juga sama (< 1e-9), jumlah posisi 1 terbanyak menang;
 *   3) bila masih sama, ditandai MUSYAWARAH dan ketua menetapkan urutan.
 */
class BordaService
{
    private const EPS = 1e-9;

    /**
     * @param  list<list<int|string>>  $votes  tiap suara = urutan lengkap alternatif dari peringkat 1
     * @param  array<int|string, float>  $groupV  V TOPSIS kelompok per alternatif
     * @return array{
     *     items: list<array{id: int|string, points: int, group_v: float, first_places: int, rank: int, tie_break_rule: ?TieBreakRule}>,
     *     needs_deliberation: bool
     * }
     */
    public function aggregate(array $votes, array $groupV): array
    {
        if ($votes === []) {
            throw new InvalidArgumentException('Tidak ada suara untuk dihitung.');
        }

        $alternatives = $votes[0];
        $m = count($alternatives);
        $this->validate($votes, $alternatives);

        $points = array_fill_keys($alternatives, 0);
        $firstPlaces = array_fill_keys($alternatives, 0);
        foreach ($votes as $vote) {
            foreach (array_values($vote) as $position => $id) {
                $points[$id] += $m - ($position + 1);
            }
            $firstPlaces[array_values($vote)[0]]++;
        }

        $items = [];
        foreach ($alternatives as $id) {
            if (! array_key_exists($id, $groupV)) {
                throw new InvalidArgumentException("V kelompok untuk alternatif {$id} tidak tersedia.");
            }
            $items[] = [
                'id' => $id,
                'points' => $points[$id],
                'group_v' => (float) $groupV[$id],
                'first_places' => $firstPlaces[$id],
                'rank' => 0,
                'tie_break_rule' => null,
            ];
        }

        // Urutan: poin ↓, V kelompok ↓, jumlah posisi 1 ↓, lalu urutan alternatif (sementara, menunggu musyawarah).
        $order = array_flip($alternatives);
        usort($items, function (array $a, array $b) use ($order) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if (abs($a['group_v'] - $b['group_v']) > self::EPS) {
                return $b['group_v'] <=> $a['group_v'];
            }
            if ($a['first_places'] !== $b['first_places']) {
                return $b['first_places'] <=> $a['first_places'];
            }

            return $order[$a['id']] <=> $order[$b['id']];
        });

        $needsDeliberation = false;
        foreach ($items as $index => &$item) {
            $item['rank'] = $index + 1;
            $item['tie_break_rule'] = $this->ruleFor($item, $items);
            $needsDeliberation = $needsDeliberation || $item['tie_break_rule'] === TieBreakRule::Musyawarah;
        }
        unset($item);

        return ['items' => $items, 'needs_deliberation' => $needsDeliberation];
    }

    /** Aturan yang menentukan posisi satu alternatif di dalam kelompok seri poinnya (null = tidak seri). */
    private function ruleFor(array $item, array $items): ?TieBreakRule
    {
        $samePoints = array_filter($items, fn ($o) => $o['id'] !== $item['id'] && $o['points'] === $item['points']);
        if ($samePoints === []) {
            return null;
        }

        $sameV = array_filter($samePoints, fn ($o) => abs($o['group_v'] - $item['group_v']) <= self::EPS);
        if ($sameV === []) {
            return TieBreakRule::VKelompok;
        }

        $sameFirst = array_filter($sameV, fn ($o) => $o['first_places'] === $item['first_places']);

        return $sameFirst === [] ? TieBreakRule::JumlahPeringkat1 : TieBreakRule::Musyawarah;
    }

    /** Setiap suara wajib memuat semua alternatif tepat satu kali (Bab 5.5). */
    private function validate(array $votes, array $alternatives): void
    {
        $expected = $alternatives;
        sort($expected);

        if (count(array_unique($alternatives)) !== count($alternatives)) {
            throw new InvalidArgumentException('Suara pertama memuat alternatif ganda.');
        }

        foreach ($votes as $index => $vote) {
            $actual = array_values($vote);
            sort($actual);
            if ($actual !== $expected) {
                throw new InvalidArgumentException('Suara ke-'.($index + 1).' tidak memuat seluruh alternatif tepat satu kali.');
            }
        }
    }
}
