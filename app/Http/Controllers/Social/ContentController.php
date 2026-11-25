<?php

namespace App\Http\Controllers\Social;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Content;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Services\FileStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $contents = Content::with(['client', 'files.file', 'publications.account'])
            ->when($request->query('stage'), fn ($q, $s) => $q->where('stage', $s))
            ->when($request->integer('client'), fn ($q, $c) => $q->where('client_id', $c))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('description', 'like', "%{$t}%")))
            ->latest('updated_at')->paginate(24)->withQueryString();

        return view('social.contents.index', ['contents' => $contents, 'clients' => Client::orderBy('name')->get()]);
    }

    public function create(Request $request)
    {
        return $this->form(new Content(['client_id' => $request->integer('client') ?: null, 'stage' => $request->query('stage', 'idea'),
            'campaign_id' => $request->integer('campaign') ?: null]));
    }

    public function edit(Content $content)
    {
        return $this->form($content->load(['publications', 'files.file']));
    }

    private function form(Content $content)
    {
        return view('social.contents.form', [
            'content' => $content,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'accounts' => SocialAccount::where('is_active', true)->orderBy('name')->get(),
            'campaigns' => \App\Models\Campaign::where('status', '!=', 'finished')->orWhere('id', $content->campaign_id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, FileStore $files)
    {
        $content = DB::transaction(function () use ($request, $files) {
            $content = Content::create($this->validated($request) + ['created_by' => $request->user()->id]);
            $files->attachUploads($request->input('uploads', []), $request->user(), $content);
            $this->syncPublications($request, $content);

            return $content;
        });

        return redirect()->route('social.contents.edit', $content)->with('status', __('ui.saved'));
    }

    public function update(Request $request, Content $content, FileStore $files)
    {
        DB::transaction(function () use ($request, $content, $files) {
            $content->update($this->validated($request));
            $files->attachUploads($request->input('uploads', []), $request->user(), $content);
            $this->syncPublications($request, $content);
        });

        return back()->with('status', __('ui.saved'));
    }

    public function destroy(Content $content)
    {
        $content->delete();

        return redirect()->route('social.contents.index')->with('status', __('ui.deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(Content::TYPES)],
            'format' => ['required', Rule::in(Content::FORMATS)],
            'stage' => ['required', Rule::in(Content::STAGES)],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('client_id', $request->input('client_id'))],
        ]);
    }

    /** Publikacje tej treści na poszczególnych kontach (dodawanie, zmiana, usuwanie). */
    private function syncPublications(Request $request, Content $content): void
    {
        $rows = $request->validate([
            'pubs' => ['array'],
            'pubs.*.id' => ['nullable', 'integer'],
            'pubs.*.remove' => ['nullable', 'boolean'],
            'pubs.*.social_account_id' => ['required_with:pubs.*.scheduled_at', 'nullable', Rule::exists('social_accounts', 'id')->where('client_id', $content->client_id)],
            'pubs.*.scheduled_at' => ['nullable', 'date'],
            'pubs.*.status' => ['nullable', Rule::in(['planned', 'published', 'cancelled'])],
            'pubs.*.url' => ['nullable', 'url', 'max:500'],
            'pubs.*.caption' => ['nullable', 'string', 'max:5000'],
            'pubs.*.promoted' => ['nullable', 'boolean'],
            'pubs.*.promo_budget' => ['nullable', 'numeric', 'min:0'],
            'pubs.*.promo_from' => ['nullable', 'date'],
            'pubs.*.promo_to' => ['nullable', 'date'],
            'pubs.*.visible_to_client' => ['nullable', 'boolean'],
        ])['pubs'] ?? [];

        foreach ($rows as $row) {
            $pub = ! empty($row['id']) ? $content->publications()->find($row['id']) : null;
            if (! empty($row['remove'])) {
                $pub?->delete();

                continue;
            }
            if (empty($row['social_account_id']) || empty($row['scheduled_at'])) {
                continue;
            }
            $account = SocialAccount::find($row['social_account_id']);
            $status = $row['status'] ?? 'planned';
            $data = [
                'social_account_id' => $account->id,
                'platform' => $account->platform->value,
                'scheduled_at' => $row['scheduled_at'],
                'status' => $status,
                'url' => $row['url'] ?? null,
                'caption' => $row['caption'] ?? null,
                'promoted' => ! empty($row['promoted']),
                'promo_budget' => $row['promo_budget'] ?? null,
                'promo_from' => $row['promo_from'] ?? null,
                'promo_to' => $row['promo_to'] ?? null,
                'visible_to_client' => ! empty($row['visible_to_client']),
            ];
            $pub ??= new Publication(['content_id' => $content->id]);
            $pub->content_id = $content->id;
            $pub->fill($data);
            if ($status === 'published' && ! $pub->published_at) {
                $pub->published_at = min(now(), $pub->scheduled_at);
            }
            if ($status !== 'published') {
                $pub->published_at = null;
            }
            $pub->save();
            app(\App\Services\AdService::class)->syncFromPublication($pub);
        }

        // Treść z zaplanowaną publikacją jest gotowa.
        if ($content->stage !== 'ready' && $content->publications()->exists()) {
            $content->update(['stage' => 'ready']);
        }
    }
}
