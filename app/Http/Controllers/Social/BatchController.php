<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Content;
use App\Models\Publication;
use App\Models\SocialAccount;
use App\Services\FileStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Planowanie seryjne: wiele postów jednego klienta na jednym ekranie.
 * Wspólne ustawienia (konta z godzinami, hashtagi, akceptacja klienta) + wiersze z datą, tekstem i plikami.
 */
class BatchController extends Controller
{
    public function create(Request $request)
    {
        $clients = Client::where('is_active', true)->orderBy('name')->get();
        $client = $clients->firstWhere('id', $request->integer('client')) ?? null;

        return view('social.batch', [
            'clients' => $clients,
            'client' => $client,
            'defaults' => $client?->socialDefaults(),
            'accounts' => $client ? SocialAccount::where('client_id', $client->id)->where('is_active', true)->orderBy('platform')->get() : collect(),
            'campaigns' => $client ? Campaign::where('client_id', $client->id)->where('status', '!=', 'finished')->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(Request $request, FileStore $files)
    {
        $client = Client::findOrFail($request->integer('client_id'));
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'accounts' => ['array'],
            'accounts.*.on' => ['nullable', 'boolean'],
            'accounts.*.time' => ['nullable', 'date_format:H:i'],
            'type' => ['required', Rule::in(Content::TYPES)],
            'format' => ['required', Rule::in(Content::FORMATS)],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('client_id', $client->id)],
            'hashtags' => ['nullable', 'string', 'max:500'],
            'hashtag_sets' => ['nullable', 'string', 'max:3000'],
            'visible_to_client' => ['nullable', 'boolean'],
            'auto_publish' => ['nullable', 'boolean'],
            'remember' => ['nullable', 'boolean'],
            'rows' => ['required', 'array', 'max:60'],
            'rows.*.date' => ['nullable', 'date_format:Y-m-d'],
            'rows.*.time' => ['nullable', 'date_format:H:i'],
            'rows.*.title' => ['nullable', 'string', 'max:200'],
            'rows.*.description' => ['nullable', 'string', 'max:5000'],
            'rows.*.hashtags' => ['nullable', 'string', 'max:500'],
            'rows.*.uploads' => ['nullable', 'array', 'max:20'],
            'rows.*.uploads.*' => ['integer'],
        ]);

        $accounts = SocialAccount::where('client_id', $client->id)->whereIn('id', array_keys(array_filter($data['accounts'] ?? [], fn ($a) => ! empty($a['on']))))->get();
        $rows = collect($data['rows'])->filter(fn ($r) => filled($r['title'] ?? null) || filled($r['description'] ?? null) || ! empty($r['uploads']));
        $missingDate = $rows->filter(fn ($r) => $accounts->isNotEmpty() && empty($r['date']))->keys();
        if ($rows->isEmpty()) {
            return back()->withInput()->withErrors(['rows' => __('social.batch_empty')]);
        }
        if ($missingDate->isNotEmpty()) {
            return back()->withInput()->withErrors($missingDate->mapWithKeys(fn ($k) => ["rows.{$k}.date" => __('social.batch_need_date')])->all());
        }

        $created = DB::transaction(function () use ($rows, $data, $accounts, $client, $request, $files) {
            $n = 0;
            foreach ($rows as $r) {
                $title = $r['title'] ?? null;
                if (blank($title)) {
                    // Tytuł z opisu albo z nazwy pierwszego pliku — żeby nie trzeba było wpisywać.
                    $firstFile = ! empty($r['uploads']) ? \App\Models\FileUsage::find($r['uploads'][0])?->name : null;
                    $title = filled($r['description'] ?? null) ? Str::limit(strtok($r['description'], "\n"), 70) : pathinfo((string) $firstFile, PATHINFO_FILENAME);
                }
                $content = Content::create([
                    'client_id' => $client->id, 'campaign_id' => $data['campaign_id'] ?? null, 'created_by' => $request->user()->id,
                    'title' => Str::limit($title ?: __('social.batch_untitled'), 200, ''), 'description' => $r['description'] ?? null,
                    'hashtags' => filled($r['hashtags'] ?? null) ? $r['hashtags'] : ($data['hashtags'] ?? null),
                    'type' => $data['type'], 'format' => $data['format'], 'stage' => $accounts->isNotEmpty() ? 'ready' : 'preparing',
                ]);
                $files->attachUploads($r['uploads'] ?? [], $request->user(), $content);
                foreach ($accounts as $acc) {
                    $time = $r['time'] ?? ($data['accounts'][$acc->id]['time'] ?? null) ?: '12:00';
                    Publication::create(['content_id' => $content->id, 'social_account_id' => $acc->id, 'platform' => $acc->platform->value,
                        'scheduled_at' => Carbon::createFromFormat('Y-m-d H:i', $r['date'].' '.$time), 'status' => 'planned',
                        'visible_to_client' => $request->boolean('visible_to_client'),
                        'auto_publish' => $request->boolean('auto_publish') && $acc->isConnected()]);
                }
                $n++;
            }

            if ($request->boolean('remember')) {
                $client->forceFill(['social_defaults' => [
                    'accounts' => $accounts->map(fn ($a) => ['id' => $a->id, 'time' => $data['accounts'][$a->id]['time'] ?? '12:00'])->values()->all(),
                    'hashtags' => $data['hashtags'] ?? '',
                    'hashtag_sets' => self::parseSets($data['hashtag_sets'] ?? ''),
                    'visible_to_client' => $request->boolean('visible_to_client'),
                    'auto_publish' => $request->boolean('auto_publish'),
                    'type' => $data['type'], 'format' => $data['format'],
                ]])->save();
            }

            return $n;
        });

        $first = $rows->pluck('date')->filter()->sort()->first();

        return redirect()->route('social.calendar', array_filter(['month' => $first ? substr($first, 0, 7) : null]))
            ->with('status', trans_choice('social.batch_created', $created, ['n' => $created]));
    }

    /** „Nazwa: #a #b” w kolejnych liniach → [{name, tags}]. */
    public static function parseSets(string $text): array
    {
        return collect(preg_split('/\R/', $text))->map(fn ($l) => trim($l))->filter()
            ->map(function ($l) {
                [$name, $tags] = str_contains($l, ':') ? array_map('trim', explode(':', $l, 2)) : [Str::limit($l, 20, ''), $l];

                return ['name' => Str::limit($name, 40, ''), 'tags' => Str::limit($tags, 500, '')];
            })->take(20)->values()->all();
    }

    public static function setsToText(array $sets): string
    {
        return collect($sets)->map(fn ($s) => $s['name'].': '.$s['tags'])->implode("\n");
    }
}
