<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TaskSet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Wstawianie zestawu zadań dla klienta (terminy „+N” od daty startu), opcjonalnie przy kampanii. */
class TaskSetService
{
    public function __construct(private TicketService $tickets) {}

    public static function parser(User $me, ?Carbon $base = null): TaskParser
    {
        return new TaskParser(Client::where('is_active', true)->get(['id', 'name']),
            User::where('role', 'admin')->where('is_active', true)->get(['id', 'name']), $me->id, $base);
    }

    public function apply(TaskSet $set, int $clientId, Carbon $start, User $by, ?int $campaignId = null): int
    {
        $items = array_values(array_filter(self::parser($by, $start->copy()->startOfDay())->parseMany($set->body, $clientId), fn ($i) => $i['title'] !== ''));
        foreach ($items as &$i) {
            $i['client_id'] = $clientId; // zestaw zawsze dla wybranego klienta
        }
        $n = $this->createMany($by, $items, array_filter(['campaign_id' => $campaignId]));
        $set->increment('uses');

        return $n;
    }

    /** Zadania z rozpoznanych linijek (TaskParser) — w jednej transakcji. */
    public function createMany(User $by, array $items, array $extra = []): int
    {
        return DB::transaction(function () use ($by, $items, $extra) {
            foreach ($items as $i) {
                $t = $this->tickets->create($by, $extra + ['title' => mb_substr($i['title'], 0, 200), 'client_id' => $i['client_id'], 'type' => 'task',
                    'due_on' => $i['due_on']?->toDateString(), 'assigned_to' => $i['assigned_to'], 'priority' => $i['priority']], null);
                if ($i['estimate']) {
                    $t->forceFill(['estimate_minutes' => $i['estimate']])->save();
                }
            }

            return count($items);
        });
    }
}
