<?php

namespace App\Services;

/**
 * Import podziału odbiorców z eksportu menedżera reklam (Meta/TikTok/Google, PL i EN):
 * kolumny wymiaru (wiek, płeć, region/miasto, kraj) + miara (zasięg albo wyświetlenia). Udziały w %.
 */
class AudienceImport
{
    private const DIMS = [
        'age' => ['wiek', 'age'],
        'gender' => ['płeć', 'plec', 'gender'],
        'city' => ['region', 'miasto', 'city', 'lokalizacja', 'location', 'dma'],
        'country' => ['kraj', 'country'],
    ];

    private const METRICS = ['zasięg', 'reach', 'wyświetlenia', 'impressions', 'kliknięcia', 'clicks'];

    /** @return list<array{0: string, 1: string, 2: float}> [wymiar, etykieta, udział %] */
    public static function parse(string $csv): array
    {
        $lines = array_values(array_filter(preg_split('/\R/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv))), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            return [];
        }
        $sep = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : (substr_count($lines[0], "\t") ? "\t" : ',');
        $header = array_map(fn ($h) => mb_strtolower(trim($h, " \"")), str_getcsv(array_shift($lines), $sep));
        $metric = null;
        foreach (self::METRICS as $m) {
            if (($i = array_search($m, $header, true)) !== false) {
                $metric = $i;
                break;
            }
        }
        if ($metric === null) {
            return [];
        }
        $dims = [];
        foreach (self::DIMS as $dim => $names) {
            foreach ($names as $n) {
                if (($i = array_search($n, $header, true)) !== false) {
                    $dims[$dim] = $i;
                    break;
                }
            }
        }
        $sums = [];
        foreach ($lines as $line) {
            $cols = str_getcsv($line, $sep);
            $value = (float) str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string) ($cols[$metric] ?? '0'));
            if ($value <= 0) {
                continue;
            }
            foreach ($dims as $dim => $i) {
                $label = self::label($dim, trim((string) ($cols[$i] ?? '')));
                if ($label === null) {
                    continue;
                }
                $sums[$dim][$label] = ($sums[$dim][$label] ?? 0) + $value;
            }
        }
        $rows = [];
        foreach ($sums as $dim => $labels) {
            $total = array_sum($labels);
            arsort($labels);
            foreach (array_slice($labels, 0, 20, true) as $label => $v) {
                $rows[] = [$dim, $label, round($v / $total * 100, 2)];
            }
        }

        return $rows;
    }

    private static function label(string $dim, string $v): ?string
    {
        if ($v === '' || in_array(mb_strtolower($v), ['unknown', 'nieznane', 'nieznana', 'total', 'razem'], true)) {
            return null;
        }
        if ($dim === 'gender') {
            return match (true) {
                (bool) preg_match('/^(f|female|k|kobiet|kobieta|women)/iu', $v) => 'female',
                (bool) preg_match('/^(m|male|mężczy|men)/iu', $v) => 'male',
                default => null,
            };
        }
        if ($dim === 'age') {
            return preg_match('/^(\d{2})\s*[-–]\s*(\d{2})$/u', $v, $m) ? $m[1].'-'.$m[2] : (preg_match('/^65\+?$|^65 i więcej/u', $v) ? '65+' : null);
        }

        return mb_substr(preg_replace('/,\s*(Poland|Polska)$/iu', '', $v), 0, 80);
    }
}
