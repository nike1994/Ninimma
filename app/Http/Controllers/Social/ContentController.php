<?php

namespace App\Http\Controllers\Social;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Content;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Services\FileStore;
use App\Services\LicenseGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        // Z kalendarza: kliknięty dzień (i godzina) oraz konto z filtra trafiają do pierwszej publikacji.
        $account = $request->integer('account') ? SocialAccount::find($request->integer('account')) : null;
        $date = $request->query('date');
        $prefill = null;
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $time = preg_match('/^\d{2}:\d{2}$/', (string) $request->query('time')) ? $request->query('time') : '12:00';
            $prefill = ['scheduled_at' => $date.'T'.$time, 'social_account_id' => $account?->id, 'status' => 'planned', 'auto_publish' => 1];
        }

        $clientId = $request->integer('client') ?: $account?->client_id;
        $defaults = $clientId ? Client::find($clientId)?->socialDefaults() : null;
        $content = new Content(['client_id' => $clientId, 'stage' => $request->query('stage', $prefill ? 'preparing' : 'idea'),
            'campaign_id' => $request->integer('campaign') ?: null]);
        // Ustawienia domyślne klienta: hashtagi, typ, format i publikacje na zwykłych kontach o zwykłych godzinach.
        $rows = null;
        if ($defaults) {
            $content->fill(['hashtags' => $defaults['hashtags'] ?: null, 'type' => $defaults['type'], 'format' => $defaults['format']]);
            if (! $account && $defaults['accounts']) {
                $day = $prefill ? substr($prefill['scheduled_at'], 0, 10) : null;
                $rows = collect($defaults['accounts'])->map(fn ($a) => ['social_account_id' => $a['id'], 'status' => 'planned',
                    'scheduled_at' => $day ? $day.'T'.($request->query('time') ?: $a['time']) : null,
                    'visible_to_client' => $defaults['visible_to_client'] ? 1 : null, 'auto_publish' => $defaults['auto_publish'] ? 1 : null])->all();
            }
        }

        return $this->form($content, $prefill, $rows);
    }

    /** Kopia treści jako punkt wyjścia dla podobnego posta (bez publikacji; pliki i materiały te same). */
    public function duplicate(Request $request, Content $content)
    {
        $copy = DB::transaction(function () use ($request, $content) {
            $copy = $content->replicate(['created_at', 'updated_at']);
            $copy->fill(['title' => Str::limit($content->title.' '.__('social.copy_suffix'), 200, ''), 'stage' => 'preparing']);
            $copy->created_by = $request->user()->id;
            $copy->save();
            foreach ($content->files as $f) {
                \App\Models\FileUsage::create(['file_id' => $f->file_id, 'name' => $f->name, 'uploaded_by' => $request->user()->id,
                    'usable_type' => 'content', 'usable_id' => $copy->id]);
            }
            $copy->materials()->sync($content->materials()->pluck('materials.id'));

            return $copy;
        });

        return redirect()->route('social.contents.edit', $copy)->with('status', __('social.duplicated'));
    }

    public function edit(Content $content)
    {
        return $this->form($content->load(['publications', 'files.file', 'materials']));
    }

    private function form(Content $content, ?array $prefill = null, ?array $defaultRows = null)
    {
        return view('social.contents.form', [
            'content' => $content,
            'prefill' => $prefill,
            'defaultRows' => $defaultRows,
            'hashtagSets' => Client::whereNotNull('social_defaults')->get()->mapWithKeys(fn ($c) => [$c->id => $c->socialDefaults()['hashtag_sets']])->filter(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'accounts' => SocialAccount::where('is_active', true)->orderBy('name')->get(),
            'campaigns' => \App\Models\Campaign::where('status', '!=', 'finished')->orWhere('id', $content->campaign_id)->orderBy('name')->get(),
            'materials' => LicenseGuard::pickerMaterials($content->exists ? $content->materials->pluck('id')->all() : []),
            'selectedMaterials' => $content->exists ? $content->materials->pluck('id')->all() : array_filter([request()->integer('material')]),
            'licenseIssues' => $content->exists ? app(LicenseGuard::class)->issuesForContent($content) : collect(),
        ]);
    }

    public function store(Request $request, FileStore $files)
    {
        $content = DB::transaction(function () use ($request, $files) {
            $content = Content::create($this->validated($request) + ['created_by' => $request->user()->id]);
            $this->syncMaterials($request, $content);
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
            $this->syncMaterials($request, $content);
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

    /** Materiały influencerów/UGC użyte w treści (licencja sprawdzana przy publikacjach). */
    private function syncMaterials(Request $request, Content $content): void
    {
        $ids = $request->validate(['material_ids' => ['array'], 'material_ids.*' => ['integer', 'exists:materials,id']])['material_ids'] ?? [];
        $this->attachedMaterials = $content->materials()->sync($ids)['attached'];
    }

    /** Materiały dopięte w tym zapisie (wymuszają sprawdzenie licencji istniejących promocji). */
    private array $attachedMaterials = [];

    /**
     * Licencja materiałów: po wygaśnięciu nie można zaplanować ani opublikować nowego posta z materiałem (także fragmentem)
     * ani go promować. Już opublikowane posty bez zmian nie są blokowane (pilnuje ich lista konfliktów).
     */
    private function checkLicense(Publication $pub, $materials, int|string $i, array &$errors): void
    {
        if ($materials->isEmpty() || $pub->status === 'cancelled') {
            return;
        }
        $newPost = ! $pub->exists || $pub->isDirty(['scheduled_at', 'status']) || $pub->status === 'planned';
        if ($newPost && ($pub->status === 'planned' || $pub->getOriginal('status') !== 'published')) {
            foreach (LicenseGuard::publishBlockers($materials, $pub->scheduled_at) as $msg) {
                $errors['pubs.'.$i.'.scheduled_at'][] = $msg;
            }
        }
        $promoChanged = ! $pub->exists || $pub->isDirty(['promoted', 'promo_from', 'promo_to', 'scheduled_at']) || $this->attachedMaterials;
        if ($pub->promoted && $promoChanged) {
            $promoMaterials = $pub->exists && ! $pub->isDirty(['promoted', 'promo_from', 'promo_to', 'scheduled_at'])
                ? $materials->whereIn('id', $this->attachedMaterials) : $materials;
            foreach (LicenseGuard::promoteBlockers($promoMaterials, $pub->promo_from ?? $pub->scheduled_at, $pub->promo_to) as $msg) {
                $errors['pubs.'.$i.'.promoted'][] = $msg;
            }
        }
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
            'pubs.*.auto_publish' => ['nullable', 'boolean'],
        ])['pubs'] ?? [];

        $materials = $content->materials()->get();
        $licenseErrors = [];
        foreach ($rows as $i => $row) {
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
                // Automatyczna publikacja tylko dla kont połączonych z API (inaczej zawsze ręcznie).
                'auto_publish' => ! empty($row['auto_publish']) && $account->isConnected(),
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
            // Zmiana terminu, konta, opisu albo włącznika = nowa szansa dla automatycznej publikacji.
            if ($pub->status === 'planned' && $pub->api_status !== 'processing' && $pub->isDirty(['scheduled_at', 'social_account_id', 'caption', 'auto_publish'])) {
                $pub->forceFill(['api_status' => null, 'api_error' => null, 'api_attempts' => 0, 'api_next_attempt_at' => null, 'api_container' => null]);
            }
            $this->checkLicense($pub, $materials, $i, $licenseErrors);
            // Limity platformy (znaki, hashtagi) — inaczej publikacja i tak by się nie udała.
            if ($status !== 'cancelled' && ($pub->isDirty() || ! $pub->exists || $content->wasChanged(['description', 'hashtags']))) {
                foreach (\App\Support\PlatformLimits::check($account->platform, \App\Support\PlatformLimits::textFor($pub->caption, $content->description, $content->hashtags)) as $msg) {
                    $licenseErrors['pubs.'.$i.'.caption'][] = $msg;
                }
            }
            $pub->save();
            app(\App\Services\AdService::class)->syncFromPublication($pub);
        }

        if ($licenseErrors) {
            throw \Illuminate\Validation\ValidationException::withMessages($licenseErrors);
        }

        // Treść z zaplanowaną publikacją jest gotowa.
        if ($content->stage !== 'ready' && $content->publications()->exists()) {
            $content->update(['stage' => 'ready']);
        }
    }
}
