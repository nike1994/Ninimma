<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Backlink;
use App\Models\SeoPhrase;
use App\Models\SeoTask;
use App\Models\Site;
use App\Services\SiteService;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeoController extends Controller
{
    public function storePhrase(Request $request, Site $site)
    {
        $site->phrases()->create($request->validate([
            'phrase' => ['required', 'string', 'max:190'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', 'integer', 'between:1,3'],
        ]));

        return back()->with('status', __('ui.saved'));
    }

    /** Jeden ekran: pozycje wszystkich fraz z dzisiejszą datą (bez nadpisywania). */
    public function storePositions(Request $request, Site $site)
    {
        $data = $request->validate(['measured_on' => ['required', 'date'], 'pos' => ['array'], 'pos.*' => ['nullable', 'integer', 'min:1', 'max:1000']]);
        $n = 0;
        foreach ($data['pos'] ?? [] as $phraseId => $pos) {
            if ($pos === null || $pos === '') {
                continue;
            }
            $phrase = $site->phrases()->findOrFail($phraseId);
            $phrase->positions()->create(['measured_on' => $data['measured_on'], 'position' => (int) $pos]);
            $n++;
        }

        return back()->with('status', trans_choice('site.positions_saved', $n, ['n' => $n]));
    }

    public function import(Request $request, Site $site, SiteService $sites)
    {
        $request->validate(['csv' => ['required', 'file', 'max:2048']]);
        $n = $sites->importPositions($site, $request->file('csv')->get());

        return back()->with('status', trans_choice('site.positions_saved', $n, ['n' => $n]));
    }

    /** Pozycja planu treści = zlecenie (rozmowa, akceptacja klienta, historia). */
    public function storeContent(Request $request, Site $site, TicketService $tickets)
    {
        $data = $request->validate([
            'working_title' => ['required', 'string', 'max:200'],
            'seo_phrase_id' => ['nullable', Rule::exists('seo_phrases', 'id')->where('site_id', $site->id)],
            'due_on' => ['nullable', 'date'],
        ]);
        $phrase = isset($data['seo_phrase_id']) ? SeoPhrase::find($data['seo_phrase_id']) : null;
        $ticket = $tickets->create($request->user(), [
            'client_id' => $site->client_id, 'site_id' => $site->id, 'type' => 'seo_content', 'title' => $data['working_title'], 'due_on' => $data['due_on'] ?? null,
        ], $phrase ? __('site.content_body', ['phrase' => $phrase->phrase, 'url' => $phrase->target_url ?? '—']) : null);
        $site->seoContents()->create($data + ['ticket_id' => $ticket->id]);

        return back()->with('status', __('ui.saved'));
    }

    public function storeTask(Request $request, Site $site)
    {
        $site->seoTasks()->create($request->validate(['description' => ['required', 'string', 'max:255'], 'priority' => ['required', 'integer', 'between:1,3']]));

        return back();
    }

    public function updateTask(Request $request, SeoTask $task)
    {
        $task->update($request->validate(['status' => ['required', Rule::in(['todo', 'doing', 'done'])]]));

        return back();
    }

    public function storeBacklink(Request $request, Site $site)
    {
        $site->backlinks()->create($request->validate([
            'url' => ['required', 'url', 'max:255'],
            'placed_on' => ['nullable', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]) + ['checked_on' => today()]);

        return back();
    }

    public function toggleBacklink(Backlink $backlink)
    {
        $backlink->update(['is_active' => ! $backlink->is_active, 'checked_on' => today()]);

        return back();
    }
}
