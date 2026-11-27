<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TicketService;
use App\Services\TimeTracker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Czas pracy: mój tydzień (siatka dni), zespół (kto ile w tygodniu), klienci w miesiącu (godziny i wartość), eksport CSV. */
class TimeController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $tab = in_array($request->query('tab'), ['week', 'team', 'clients'], true) ? $request->query('tab') : 'week';
        $monday = $this->monday($request->query('week'));
        $month = $this->month($request->query('month'));
        $team = User::where('role', 'admin')->where('is_active', true)->orderBy('name')->get();
        $person = $team->firstWhere('id', $request->integer('user')) ?? $me;

        $data = ['tab' => $tab, 'monday' => $monday, 'month' => $month, 'team' => $team, 'person' => $person, 'me' => $me];
        if ($tab === 'week') {
            $data['week'] = TimeTracker::week($person, $monday);
            $data['tickets'] = Ticket::with('client')->open()->where('archive_state', 'active')
                ->orderByRaw('assigned_to = ? desc', [$me->id])->latest('last_activity_at')->limit(150)->get();
        } elseif ($tab === 'team') {
            $entries = TimeEntry::whereBetween('spent_on', [$monday->toDateString(), $monday->copy()->addDays(6)->toDateString()])->get();
            $data['rows'] = $team->map(fn ($u) => (object) [
                'user' => $u,
                'days' => $entries->where('user_id', $u->id)->groupBy(fn ($e) => $e->spent_on->toDateString())->map->sum('minutes'),
                'total' => (int) $entries->where('user_id', $u->id)->sum('minutes'),
                'billable' => (int) $entries->where('user_id', $u->id)->where('billable', true)->sum('minutes'),
                'timer' => $u->timer_ticket_id ? $u->timerTicket : null,
            ]);
        } else {
            $data['clients'] = $this->clientSummary($month);
        }

        return view('time.index', $data);
    }

    /** Ręczny wpis czasu (np. za wczoraj) z wyborem zlecenia. */
    public function store(Request $request, TicketService $tickets)
    {
        $data = $request->validate([
            'ticket_id' => ['required', 'exists:tickets,id'],
            'hours' => ['required', 'regex:/^\d{1,3}([:.,]\d{1,2})?$/'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:250'],
        ]);
        $minutes = TicketController::parseHours($data['hours']);
        abort_if($minutes < 1 || $minutes > 24 * 60, 422);
        $tickets->logTime(Ticket::findOrFail($data['ticket_id']), $request->user(), $minutes, $data['spent_on'], $data['note'] ?? null, ! $request->boolean('internal'));

        return back()->with('status', __('ui.saved'));
    }

    public function update(Request $request, TimeEntry $entry)
    {
        $data = $request->validate([
            'hours' => ['required', 'regex:/^\d{1,3}([:.,]\d{1,2})?$/'],
            'note' => ['nullable', 'string', 'max:250'],
        ]);
        $minutes = TicketController::parseHours($data['hours']);
        abort_if($minutes < 1 || $minutes > 24 * 60, 422);
        $entry->update(['minutes' => $minutes, 'note' => $data['note'] ?? null, 'billable' => ! $request->boolean('internal')]);

        return back()->with('status', __('ui.saved'));
    }

    public function destroy(TimeEntry $entry)
    {
        $entry->delete();

        return back()->with('status', __('task.entry_deleted'));
    }

    /** Eksport miesiąca do arkusza (CSV, średniki, UTF-8 z BOM — otwiera się w Excelu). */
    public function export(Request $request)
    {
        $month = $this->month($request->query('month'));
        $entries = TimeEntry::with(['ticket.client', 'user'])->whereBetween('spent_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('spent_on')->get();
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [__('ui.date'), __('task.person'), __('ui.client'), __('task.ticket'), __('task.note'), __('task.hours'), __('task.billable'), __('task.rate'), __('task.value')], ';');
        foreach ($entries as $e) {
            $rate = $e->ticket->client->rate();
            fputcsv($out, [$e->spent_on->format('Y-m-d'), $e->user?->name, $e->ticket->client->name, '#'.$e->ticket_id.' '.$e->ticket->title, $e->note,
                str_replace('.', ',', (string) round($e->minutes / 60, 2)), $e->billable ? __('task.yes') : __('task.no'),
                str_replace('.', ',', (string) $rate), $e->billable ? str_replace('.', ',', (string) round($e->minutes / 60 * $rate, 2)) : '0'], ';');
        }
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="czas-pracy-'.$month->format('Y-m').'.csv"',
        ]);
    }

    /** Klienci w miesiącu: godziny (wszystkie i rozliczalne), stawka, wartość, pakiet opieki stron, zlecenia. */
    private function clientSummary(Carbon $month): Collection
    {
        $entries = TimeEntry::with('ticket.client', 'ticket.site')->whereBetween('spent_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get();

        return $entries->groupBy(fn ($e) => $e->ticket->client_id)->map(function (Collection $es) {
            $client = $es->first()->ticket->client;
            $billable = (int) $es->where('billable', true)->sum('minutes');
            $care = (float) $client->sites()->sum('care_hours');

            return (object) [
                'client' => $client, 'total' => (int) $es->sum('minutes'), 'billable' => $billable, 'rate' => $client->rate(),
                'value' => round($billable / 60 * $client->rate(), 2), 'care_minutes' => $care > 0 ? (int) round($care * 60) : null,
                // Pakiet opieki obejmuje prace przy stronach (zlecenia przypięte do strony).
                'care_used' => (int) $es->filter(fn ($e) => $e->ticket->site_id !== null)->sum('minutes'),
                'tickets' => $es->groupBy('ticket_id')->map(fn ($t) => (object) ['ticket' => $t->first()->ticket, 'total' => (int) $t->sum('minutes')])->sortByDesc('total')->values(),
            ];
        })->sortByDesc('total')->values();
    }

    private function monday(?string $date): Carbon
    {
        try {
            return ($date ? Carbon::parse($date) : today())->startOfWeek();
        } catch (\Throwable) {
            return today()->startOfWeek();
        }
    }

    private function month(?string $ym): Carbon
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $ym) ? Carbon::createFromFormat('Y-m-d', $ym.'-01')->startOfDay() : today()->startOfMonth();
    }
}
