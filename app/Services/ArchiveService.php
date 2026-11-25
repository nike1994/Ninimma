<?php

namespace App\Services;

use App\Models\Board;
use App\Models\BoardFile;
use App\Models\BoardVersion;
use App\Models\Client;
use App\Models\ExportPackage;
use App\Models\FileUsage;
use App\Models\Setting;
use App\Models\StoredFile;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Pliki i archiwizacja: rok po zamknięciu zlecenie jest odchudzane na miejscu,
 * najstarsze można przenieść offline (paczka do homelabu), a po okresie przechowywania — usunąć.
 */
class ArchiveService
{
    public function __construct(private TicketService $tickets, private FileStore $store) {}

    private function disk()
    {
        return Storage::disk('local');
    }

    /** Użycia plików należące do rekordów zlecenia (wiadomości, tablice, podglądy wersji). */
    public function ticketUsages(Ticket $ticket): Collection
    {
        $messageIds = $ticket->messages()->pluck('id');
        $boardIds = $ticket->boards()->pluck('id');
        $versionIds = BoardVersion::whereIn('board_id', $boardIds)->pluck('id');

        return FileUsage::with('file')->where(function ($q) use ($messageIds, $boardIds, $versionIds) {
            $q->where(fn ($w) => $w->where('usable_type', 'ticket_message')->whereIn('usable_id', $messageIds))
                ->orWhere(fn ($w) => $w->where('usable_type', 'board')->whereIn('usable_id', $boardIds))
                ->orWhere(fn ($w) => $w->where('usable_type', 'board_version')->whereIn('usable_id', $versionIds));
        })->get();
    }

    /** Wszystkie pliki klienta (zlecenia, treści, materiały, dokumenty kosztowe). */
    public function clientFileIds(Client $client): Collection
    {
        $ids = collect();
        foreach ($client->tickets()->get() as $t) {
            $ids = $ids->merge($this->ticketUsages($t)->pluck('file_id'));
        }
        $contentIds = \App\Models\Content::where('client_id', $client->id)->pluck('id');
        $materialIds = \App\Models\Material::whereHas('collaboration.campaign', fn ($q) => $q->where('client_id', $client->id))->pluck('id');
        $docIds = \App\Models\CostDocument::where('client_id', $client->id)->pluck('id');
        $ids = $ids->merge(FileUsage::where(fn ($q) => $q
            ->where(fn ($w) => $w->where('usable_type', 'content')->whereIn('usable_id', $contentIds))
            ->orWhere(fn ($w) => $w->where('usable_type', 'material')->whereIn('usable_id', $materialIds))
            ->orWhere(fn ($w) => $w->where('usable_type', 'cost_document')->whereIn('usable_id', $docIds)))->pluck('file_id'));

        return $ids->unique()->values();
    }

    /** Licznik zajętości na klienta (bajty plików na hostingu). */
    public function usageByClient(): Collection
    {
        return Client::orderBy('name')->get()->map(function (Client $c) {
            $ids = $this->clientFileIds($c);

            return (object) [
                'client' => $c,
                'bytes' => (int) StoredFile::whereIn('id', $ids)->where('location', 'hosting')->sum('size'),
                'offline' => (int) StoredFile::whereIn('id', $ids)->where('location', 'offline')->sum('size'),
                'tickets' => $c->tickets()->count(),
            ];
        })->sortByDesc('bytes')->values();
    }

    public function totalBytes(): int
    {
        return (int) StoredFile::where('location', 'hosting')->sum('size');
    }

    public function readyToArchive(): Collection
    {
        return Ticket::with('client')->where('status', 'done')->where('archive_state', 'active')
            ->where('closed_at', '<', now()->subYear())->orderBy('closed_at')->get();
    }

    /** Odchudzenie: zostaje wersja końcowa i wersje opisane tablic; pośrednie znikają razem z plikami używanymi tylko przez nie. */
    public function archive(Ticket $ticket, ?User $by = null): void
    {
        DB::transaction(function () use ($ticket, $by) {
            foreach ($ticket->boards as $board) {
                $versions = $board->versions()->orderBy('id')->get();
                $final = $versions->last();
                $drop = $versions->filter(fn ($v) => $v->id !== $final?->id && ($v->is_auto || blank($v->description)));
                foreach ($drop as $v) {
                    FileUsage::where('usable_type', 'board_version')->where('usable_id', $v->id)->delete();
                    $v->delete();
                }
                // Obrazy tablicy, których nie używa ani stan bieżący, ani zachowana wersja.
                $kept = collect($board->liveElements())->merge($board->versions()->get()->flatMap(fn ($v) => $v->elements))
                    ->pluck('fileId')->filter()->unique();
                $board->files()->get()->reject(fn (BoardFile $f) => $kept->contains($f->excalidraw_id))->each(function (BoardFile $f) {
                    $usage = $f->usage;
                    $f->delete();
                    $usage?->delete();
                });
            }
            $ticket->forceFill(['archive_state' => 'archived'])->save();
            $this->tickets->event($ticket, $by, 'archived', [], true);
        });
        $this->deleteOrphanFiles();
    }

    public function archiveDue(): int
    {
        $n = 0;
        foreach ($this->readyToArchive() as $t) {
            $this->archive($t);
            $n++;
        }

        return $n;
    }

    public function restore(Ticket $ticket, User $by): void
    {
        if ($ticket->archive_state === 'offline') {
            throw new RuntimeException(__('arch.offline_needs_package', ['package' => $ticket->offline_package]));
        }
        $ticket->forceFill(['archive_state' => 'active'])->save();
        $this->tickets->event($ticket, $by, 'restored', [], true);
    }

    /** Paczka ZIP: dane (JSON) + pliki. Dla zlecenia, kilku zleceń lub całego klienta (RODO). */
    public function export(string $kind, Collection $tickets, ?Client $client, ?User $by): ExportPackage
    {
        $name = $kind.'-'.($client ? Str::slug($client->name).'-' : '').now()->format('Ymd-His').'-'.Str::lower(Str::random(4)).'.zip';
        $this->disk()->makeDirectory('exports');
        $zip = new ZipArchive;
        if ($zip->open($this->disk()->path('exports/'.$name), ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Cannot create zip');
        }
        $manifest = ['format' => 'ninimma-export-1', 'created_at' => now()->toIso8601String(), 'kind' => $kind, 'tickets' => [], 'files' => []];
        $fileIds = collect();

        foreach ($tickets as $t) {
            $t->load(['messages.user', 'events', 'quotes', 'timeEntries', 'boards.versions', 'costDocuments']);
            $manifest['tickets'][] = [
                'ticket' => $t->only(['id', 'title', 'type', 'status', 'client_id', 'created_at', 'closed_at', 'due_on', 'priority']) + ['type' => $t->type->value, 'status' => $t->status->value, 'priority' => $t->priority->value],
                'messages' => $t->messages->map(fn ($m) => ['id' => $m->id, 'author' => $m->user?->name, 'internal' => $m->is_internal, 'body' => $m->body, 'at' => $m->created_at->toIso8601String()]),
                'timeline' => $t->events->map(fn ($e) => ['type' => $e->type, 'data' => $e->data, 'internal' => $e->is_internal, 'at' => $e->created_at->toIso8601String()]),
                'quotes' => $t->quotes->map->only(['version', 'hours_limit', 'amount', 'justification', 'options', 'status', 'chosen_option', 'decided_at']),
                'time_entries' => $t->timeEntries->map->only(['spent_on', 'minutes', 'note']),
                'boards' => $t->boards->map(fn ($b) => ['name' => $b->name, 'elements' => $b->liveElements(),
                    'versions' => $b->versions->map->only(['id', 'description', 'is_auto', 'created_at', 'elements'])]),
            ];
            $fileIds = $fileIds->merge($this->ticketUsages($t)->pluck('file_id'));
        }
        if ($client) {
            $manifest['client'] = $client->only(['id', 'name', 'company_name', 'tax_id', 'address', 'contact_email', 'contact_phone', 'arrangements']);
            $manifest['users'] = $client->users()->get(['name', 'email', 'created_at', 'last_login_at', 'terms_accepted_at']);
            $manifest['contents'] = \App\Models\Content::with('publications.measurements')->where('client_id', $client->id)->get();
            $manifest['sites'] = \App\Models\Site::with(['logs', 'phrases.positions'])->where('client_id', $client->id)->get();
            $manifest['cost_documents'] = \App\Models\CostDocument::where('client_id', $client->id)->get(['number', 'amount', 'issued_on', 'is_paid']);
            $fileIds = $this->clientFileIds($client);
        }

        foreach (StoredFile::whereIn('id', $fileIds->unique())->get() as $f) {
            $usageName = FileUsage::where('file_id', $f->id)->value('name') ?? $f->sha256;
            $entry = 'files/'.substr($f->sha256, 0, 12).'_'.Str::limit(preg_replace('/[^\w.\-]+/u', '_', $usageName), 80, '');
            $manifest['files'][] = ['sha256' => $f->sha256, 'path' => $entry, 'mime' => $f->mime, 'size' => $f->size];
            if ($f->location === 'hosting' && $this->disk()->exists($f->path)) {
                $zip->addFile($this->store->absolutePath($f), $entry);
            }
        }
        $zip->addFromString('data.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $zip->close();

        $pkg = ExportPackage::create(['name' => $name, 'kind' => $kind, 'client_id' => $client?->id ?? $tickets->first()?->client_id,
            'size' => $this->disk()->size('exports/'.$name), 'ticket_ids' => $tickets->pluck('id')->values()->all(), 'created_by' => $by?->id]);
        SystemLog::record('export', 'Export package created', ['package' => $name, 'kind' => $kind]);

        return $pkg;
    }

    /** Przeniesienie offline: paczka → homelab; ciężkie pliki znikają z hostingu, w zleceniu zostaje nazwa paczki. */
    public function moveOffline(Collection $tickets, User $by): ExportPackage
    {
        $pkg = $this->export('offline', $tickets, null, $by);
        DB::transaction(function () use ($tickets, $pkg, $by) {
            foreach ($tickets as $t) {
                foreach ($this->ticketUsages($t)->pluck('file') ->filter()->unique('id') as $file) {
                    // Tylko pliki używane wyłącznie przez rekordy przenoszonych zleceń.
                    $otherUses = FileUsage::where('file_id', $file->id)->get()->reject(fn ($u) => $this->belongsToTickets($u, $tickets));
                    if ($otherUses->isEmpty() && $file->location === 'hosting') {
                        $this->disk()->delete($file->path);
                        $file->update(['location' => 'offline', 'offline_package' => $pkg->name]);
                    }
                }
                $t->forceFill(['archive_state' => 'offline', 'offline_package' => $pkg->name])->save();
                $this->tickets->event($t, $by, 'moved_offline', ['package' => $pkg->name], true);
            }
        });

        return $pkg;
    }

    private function belongsToTickets(FileUsage $u, Collection $tickets): bool
    {
        $ids = $tickets->pluck('id');

        return match ($u->usable_type) {
            'ticket_message' => $ids->contains(\App\Models\TicketMessage::whereKey($u->usable_id)->value('ticket_id')),
            'board' => $ids->contains(Board::whereKey($u->usable_id)->value('ticket_id')),
            'board_version' => $ids->contains(Board::whereKey(BoardVersion::whereKey($u->usable_id)->value('board_id'))->value('ticket_id')),
            default => false,
        };
    }

    /** Przywrócenie z paczki wgranej ręcznie (pliki dopasowywane po sumie kontrolnej). */
    public function restoreFromPackage(string $zipPath, User $by): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true || ($json = $zip->getFromName('data.json')) === false) {
            throw new RuntimeException(__('arch.bad_package'));
        }
        $manifest = json_decode($json, true);
        $restored = 0;
        foreach ($manifest['files'] ?? [] as $f) {
            $file = StoredFile::where('sha256', $f['sha256'] ?? '')->where('location', 'offline')->first();
            $content = $file ? $zip->getFromName($f['path']) : false;
            if ($file && $content !== false && hash('sha256', $content) === $file->sha256) {
                $this->disk()->put($file->path, $content);
                $file->update(['location' => 'hosting', 'offline_package' => null]);
                $restored++;
            }
        }
        $zip->close();
        $ids = collect($manifest['tickets'] ?? [])->pluck('ticket.id');
        Ticket::whereIn('id', $ids)->where('archive_state', 'offline')->get()->each(function (Ticket $t) use ($by) {
            $t->forceFill(['archive_state' => 'archived', 'offline_package' => null])->save();
            $this->tickets->event($t, $by, 'restored_from_package', [], true);
        });

        return ['files' => $restored, 'tickets' => $ids->count()];
    }

    /** Usunięcie zlecenia (po okresie przechowywania lub na wniosek klienta) wraz z plikami używanymi tylko przez nie. */
    public function deleteTicket(Ticket $ticket, ?User $by, string $reason): void
    {
        DB::transaction(function () use ($ticket, $by, $reason) {
            $this->ticketUsages($ticket)->each->delete();
            SystemLog::record('ticket_deleted', 'Ticket deleted', ['ticket' => $ticket->id, 'client' => $ticket->client_id, 'reason' => $reason, 'by' => $by?->id]);
            $ticket->delete();
        });
        $this->deleteOrphanFiles();
    }

    /** Okres przechowywania ustalany na klienta (dokumenty księgowe nie podlegają tej automatyce). */
    public function purgeExpired(): int
    {
        $n = 0;
        $default = (int) Setting::get('default_retention_months', 0);
        foreach (Client::all() as $client) {
            $months = $client->retention_months ?: $default;
            if (! $months) {
                continue;
            }
            $client->tickets()->whereIn('archive_state', ['archived', 'offline'])->where('closed_at', '<', now()->subMonths($months))
                ->get()->each(function (Ticket $t) use (&$n) {
                    $this->deleteTicket($t, null, 'retention');
                    $n++;
                });
        }

        return $n;
    }

    public function deleteOrphanFiles(): int
    {
        $n = 0;
        StoredFile::doesntHave('usages')->get()->each(function (StoredFile $f) use (&$n) {
            if ($f->location === 'hosting') {
                $this->disk()->delete($f->path);
            }
            $f->delete();
            $n++;
        });

        return $n;
    }
}
