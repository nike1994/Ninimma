<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Zadanie z jednej linijki tekstu, np. „Grafiki na BF #zielony @ola jutro 2h !”.
 *  #klient  @osoba (albo @ja)  dziś / jutro / pojutrze / pt / 15.12 / za 3 dni / +3 (dni od startu zestawu)
 *  2h, 1,5h, 30m, 1:30 = szacunek   ! = wysoki priorytet, !! = pilne
 * Rozpoznane fragmenty znikają z tytułu; reszta to tytuł zadania.
 */
class TaskParser
{
    private const WEEKDAYS = [
        'pon' => 1, 'pn' => 1, 'poniedzialek' => 1, 'mon' => 1, 'wt' => 2, 'wto' => 2, 'wtorek' => 2, 'tue' => 2,
        'sr' => 3, 'sro' => 3, 'sroda' => 3, 'wed' => 3, 'czw' => 4, 'cz' => 4, 'czwartek' => 4, 'thu' => 4,
        'pt' => 5, 'pia' => 5, 'piatek' => 5, 'fri' => 5, 'sob' => 6, 'so' => 6, 'sobota' => 6, 'sat' => 6,
        'nd' => 7, 'ndz' => 7, 'niedziela' => 7, 'sun' => 7,
    ];

    /** @param Collection $clients modele z id i name  @param Collection $team modele z id i name */
    public function __construct(private Collection $clients, private Collection $team, private int $me, private ?Carbon $base = null) {}

    /** Wiele linijek (wklejona lista): pomija puste i znaczniki wypunktowania. */
    public function parseMany(string $text, ?int $defaultClient): array
    {
        return collect(preg_split('/\R/u', $text))
            ->map(fn ($l) => trim(preg_replace('/^\s*(?:[-*•–]|\d+[.)]|\[\s?\])\s*/u', '', $l)))
            ->filter(fn ($l) => $l !== '')->take(100)
            ->map(fn ($l) => $this->parse($l, $defaultClient))->values()->all();
    }

    public function parse(string $line, ?int $defaultClient = null): array
    {
        $today = today();
        $base = $this->base ?? $today;
        $r = ['client_id' => $defaultClient, 'assigned_to' => $this->me, 'due_on' => null, 'estimate' => null, 'priority' => 'normal', 'errors' => []];
        $keep = [];
        $words = preg_split('/\s+/u', trim($line));
        for ($i = 0; $i < count($words); $i++) {
            $w = $words[$i];
            $n = $this->norm($w);
            $next = $this->norm($words[$i + 1] ?? '');
            $prev = $this->norm($words[$i - 1] ?? '');
            // „o 15:30”, „wersja 1.5” — to część tytułu, nie szacunek ani termin.
            if (in_array($prev, ['o', 'od', 'do', 'godz', 'godzina', 'na', 'at', 'wersja', 'wersji', 'v', 'ver', 'version'], true)
                && preg_match('/^\d{1,2}[:.]\d{1,2}$/', $n)) {
                $keep[] = $w;

                continue;
            }

            if ($w === '!' || $w === '!!' || $w === '!!!') {
                $r['priority'] = strlen($w) > 1 ? 'urgent' : 'high';
            } elseif (str_starts_with($w, '#') && mb_strlen($w) > 1) {
                $c = $this->match($this->clients, mb_substr($w, 1));
                $c ? $r['client_id'] = $c->id : $r['errors'][] = __('task.parse_no_client', ['x' => $w]);
            } elseif (str_starts_with($w, '@') && mb_strlen($w) > 1) {
                $who = $this->norm(mb_substr($w, 1));
                $u = in_array($who, ['ja', 'me', 'mnie'], true) ? (object) ['id' => $this->me] : $this->match($this->team, mb_substr($w, 1));
                $u ? $r['assigned_to'] = $u->id : $r['errors'][] = __('task.parse_no_person', ['x' => $w]);
            } elseif (preg_match('/^(\d{1,3}(?:[.,]\d{1,2})?)(h|g|godz)$/u', $n, $m)) {
                $r['estimate'] = (int) round((float) str_replace(',', '.', $m[1]) * 60);
            } elseif (preg_match('/^(\d{1,4})(m|min)$/u', $n, $m)) {
                $r['estimate'] = (int) $m[1];
            } elseif (preg_match('/^(\d{1,2}):(\d{2})$/', $n, $m) && (int) $m[1] <= 12) { // 13:00 i później to godzina, nie szacunek
                $r['estimate'] = (int) $m[1] * 60 + (int) $m[2];
            } elseif (in_array($n, ['dzis', 'dzisiaj', 'today'], true)) {
                $r['due_on'] = $today->copy();
            } elseif (in_array($n, ['jutro', 'tomorrow'], true)) {
                $r['due_on'] = $today->copy()->addDay();
            } elseif ($n === 'pojutrze') {
                $r['due_on'] = $today->copy()->addDays(2);
            } elseif (isset(self::WEEKDAYS[$n])) {
                $d = $today->copy();
                while ($d->dayOfWeekIso !== self::WEEKDAYS[$n]) {
                    $d->addDay();
                }
                $r['due_on'] = $d;
            } elseif (preg_match('/^\+(\d{1,3})d?$/', $n, $m)) {
                $r['due_on'] = $base->copy()->addDays((int) $m[1]);
            } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})(?:\.(\d{2,4}))?$/', $n, $m) && checkdate((int) $m[2], (int) $m[1], (int) ($m[3] ?? $today->year))) {
                $year = isset($m[3]) ? (strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3]) : $today->year;
                $d = Carbon::create($year, (int) $m[2], (int) $m[1])->startOfDay();
                $r['due_on'] = ! isset($m[3]) && $d->lt($today->copy()->subDays(7)) ? $d->addYear() : $d; // „5.01” w grudniu = przyszły rok
            } elseif ($n === 'za' && preg_match('/^\d{1,3}$/', $next) && in_array($this->norm($words[$i + 2] ?? ''), ['dni', 'dzien', 'days', 'tyg', 'tygodnie', 'tygodni'], true)) {
                $unit = $this->norm($words[$i + 2]);
                $r['due_on'] = str_starts_with($unit, 'ty') ? $today->copy()->addWeeks((int) $next) : $today->copy()->addDays((int) $next);
                $i += 2;
            } elseif ($n === 'za' && in_array($next, ['tydzien', 'week'], true)) {
                $r['due_on'] = $today->copy()->addWeek();
                $i++;
            } else {
                $keep[] = $w;
            }
        }
        $r['title'] = trim(preg_replace('/\s+/u', ' ', implode(' ', $keep)), " \t-–—,;:");
        if ($r['title'] === '') {
            $r['errors'][] = __('task.parse_no_title');
        }
        if (! $r['client_id']) {
            $r['errors'][] = __('task.parse_need_client');
        }

        return $r;
    }

    /** Dopasowanie po początku nazwy (bez polskich znaków, spacji i wielkości liter), potem po fragmencie. */
    private function match(Collection $items, string $q): ?object
    {
        $q = str_replace(' ', '', $this->norm($q));
        $key = fn ($x) => str_replace([' ', '-', '.'], '', $this->norm($x->name));

        return $items->first(fn ($x) => str_starts_with($key($x), $q))
            ?? $items->first(fn ($x) => collect(preg_split('/\s+/', $this->norm($x->name)))->contains(fn ($part) => str_starts_with($part, $q)))
            ?? (mb_strlen($q) >= 3 ? $items->first(fn ($x) => str_contains($key($x), $q)) : null);
    }

    private function norm(string $s): string
    {
        return mb_strtolower(Str::ascii(trim($s, " \t,;.")));
    }
}
