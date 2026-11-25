<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Review;
use App\Models\SeoPhrase;
use App\Models\Site;
use App\Models\SiteLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SiteService
{
    public function __construct(private TicketService $tickets) {}

    /** Przegląd cykliczny tworzy się sam jako zlecenie z listą kontrolną. */
    public function scheduleReview(Site $site, ?User $by = null): Review
    {
        $by ??= User::where('role', 'admin')->orderBy('id')->firstOrFail();
        $items = $site->checklist?->items ?? __('site.default_checklist');
        $due = $site->next_review_on ?? today();

        return DB::transaction(function () use ($site, $by, $items, $due) {
            $ticket = $this->tickets->create($by, [
                'client_id' => $site->client_id, 'site_id' => $site->id, 'type' => 'site_review',
                'title' => __('site.review_title', ['domain' => $site->domain, 'month' => $due->translatedFormat('F Y')]),
                'due_on' => $due->toDateString(),
            ], __('site.review_body')."\n- ".implode("\n- ", $items));
            $review = Review::create(['site_id' => $site->id, 'ticket_id' => $ticket->id, 'checklist_id' => $site->checklist_id,
                'due_on' => $due, 'items' => array_values($items), 'checked' => []]);
            if ($site->review_recurrence) {
                $site->update(['next_review_on' => match ($site->review_recurrence) {
                    'weekly' => $due->copy()->addWeek(),
                    'quarterly' => $due->copy()->addMonthsNoOverflow(3),
                    default => $due->copy()->addMonthNoOverflow(),
                }]);
            }

            return $review;
        });
    }

    /** Uruchamiane przez cron: zaległe przeglądy. */
    public function runDueReviews(): int
    {
        $n = 0;
        Site::where('is_active', true)->whereNotNull('review_recurrence')->whereDate('next_review_on', '<=', today())
            ->each(function (Site $site) use (&$n) {
                $this->scheduleReview($site);
                $n++;
            });

        return $n;
    }

    /** Odhaczone punkty i uwagi zapisują się w dzienniku strony. */
    public function completeReview(Review $review, User $by, array $checked, ?string $notes): void
    {
        DB::transaction(function () use ($review, $by, $checked, $notes) {
            $review->update(['checked' => $checked, 'notes' => $notes, 'completed_at' => now()]);
            [$done, $all] = $review->progress();
            $missing = collect($review->items)->reject(fn ($i, $k) => ! empty($checked[$k]))->values();
            SiteLog::create(['site_id' => $review->site_id, 'ticket_id' => $review->ticket_id, 'user_id' => $by->id, 'logged_on' => today(), 'kind' => 'review',
                'description' => __('site.review_log', ['done' => $done, 'all' => $all])
                    .($missing->isNotEmpty() ? "\n".__('site.review_missing').': '.$missing->implode(', ') : '')
                    .($notes ? "\n".$notes : '')]);
            if ($review->ticket) {
                $this->tickets->changeStatus($review->ticket, $by, TicketStatus::Done);
            }
        });
    }

    /** Problem znaleziony w przeglądzie staje się osobnym zleceniem (z wyceną). */
    public function problemFromReview(Review $review, User $by, string $title): Ticket
    {
        return $this->tickets->create($by, [
            'client_id' => $review->site->client_id, 'site_id' => $review->site_id, 'parent_id' => $review->ticket_id,
            'type' => 'bug', 'title' => $title,
        ], __('site.problem_body', ['domain' => $review->site->domain, 'date' => $review->due_on->format('d.m.Y')]));
    }

    /** Import pomiarów pozycji z pliku CSV: fraza;data;pozycja (separator ; lub ,). */
    public function importPositions(Site $site, string $csv): int
    {
        $n = 0;
        foreach (preg_split('/\R/', trim($csv)) as $line) {
            $cols = str_getcsv($line, str_contains($line, ';') ? ';' : ',');
            if (count($cols) < 3 || ! strtotime($cols[1])) {
                continue; // nagłówek lub błędny wiersz
            }
            $phrase = SeoPhrase::firstOrCreate(['site_id' => $site->id, 'phrase' => trim($cols[0])]);
            $pos = trim($cols[2]);
            $phrase->positions()->create(['measured_on' => Carbon::parse($cols[1])->toDateString(), 'position' => ctype_digit($pos) ? (int) $pos : null]);
            $n++;
        }

        return $n;
    }

    /** Raport miesięczny dla klienta: co zrobiono, zużycie pakietu, zmiany pozycji, plan treści. */
    public function monthlyReport(Site $site, Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $phrases = $site->phrases()->with(['positions' => fn ($q) => $q->whereDate('measured_on', '<=', $to)])->get()->map(function (SeoPhrase $p) use ($from) {
            $before = $p->positions->filter(fn ($x) => $x->measured_on->lt($from))->last();
            $last = $p->positions->last();
            $start = $before ?? $p->positions->first();

            return (object) ['phrase' => $p, 'position' => $last?->position, 'change' => $last && $start && $last->position && $start->position && $last !== $start ? $start->position - $last->position : null];
        });

        return [
            'logs' => $site->logs()->whereBetween('logged_on', [$from->toDateString(), $to->toDateString()])->get(),
            'minutes' => $site->minutesUsed($month),
            'package' => $site->care_hours ? (int) round((float) $site->care_hours * 60) : null,
            'phrases' => $phrases,
            'planned' => $site->seoContents()->with('ticket')->whereBetween('due_on', [$to->copy()->addDay()->toDateString(), $to->copy()->addMonthNoOverflow()->endOfMonth()->toDateString()])->get(),
            'reviews' => $site->reviews()->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])->get(),
        ];
    }
}
