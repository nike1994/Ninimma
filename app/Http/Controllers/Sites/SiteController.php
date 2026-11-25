<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\Client;
use App\Models\Review;
use App\Models\Site;
use App\Models\SiteDeadline;
use App\Models\SiteLog;
use App\Services\SiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    public function __construct(private SiteService $sites) {}

    public function index(Request $request)
    {
        return view('sites.index', [
            'sites' => Site::with(['client', 'deadlines' => fn ($q) => $q->whereNull('renewed_at')])
                ->when($request->integer('client'), fn ($q, $c) => $q->where('client_id', $c))
                ->orderByDesc('is_active')->orderBy('domain')->get(),
            'deadlines' => SiteDeadline::with('site.client')->whereNull('renewed_at')->whereDate('due_on', '<=', today()->addDays(60))->orderBy('due_on')->get(),
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return $this->form(new Site(['client_id' => $request->integer('client') ?: null, 'review_recurrence' => 'monthly', 'next_review_on' => today()->addMonthNoOverflow()->startOfMonth()]));
    }

    public function edit(Site $site)
    {
        return $this->form($site);
    }

    private function form(Site $site)
    {
        return view('sites.form', ['site' => $site, 'clients' => Client::where('is_active', true)->orderBy('name')->get(), 'checklists' => Checklist::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $site = Site::create($this->validated($request));

        return redirect()->route('sites.show', $site)->with('status', __('ui.saved'));
    }

    public function update(Request $request, Site $site)
    {
        $site->update($this->validated($request));

        return redirect()->route('sites.show', $site)->with('status', __('ui.saved'));
    }

    public function show(Request $request, Site $site)
    {
        $month = Carbon::parse($request->query('month', 'now'))->startOfMonth();

        return view('sites.show', [
            'site' => $site->load(['client', 'checklist', 'deadlines']),
            'tab' => in_array($request->query('tab'), ['overview', 'reviews', 'seo', 'report'], true) ? $request->query('tab') : 'overview',
            'month' => $month,
            'used' => $site->minutesUsed($month),
            'logs' => $site->logs()->with(['ticket', 'user'])->limit(30)->get(),
            'reviews' => $site->reviews()->with('ticket')->limit(12)->get(),
            'openTickets' => $site->tickets()->open()->latest('last_activity_at')->get(),
            'phrases' => $site->phrases()->with('positions')->get(),
            'contents' => $site->seoContents()->with(['ticket', 'phrase'])->get(),
            'tasks' => $site->seoTasks()->with('ticket')->get(),
            'backlinks' => $site->backlinks()->get(),
            'report' => $this->sites->monthlyReport($site, $month),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'domain' => ['required', 'string', 'max:190'],
            'platform' => ['required', Rule::in(Site::PLATFORMS)],
            'hosting' => ['nullable', 'string', 'max:190'],
            'care_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'checklist_id' => ['nullable', 'exists:checklists,id'],
            'review_recurrence' => ['nullable', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'next_review_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return $data + ['is_active' => $request->boolean('is_active', true)];
    }

    public function storeDeadline(Request $request, Site $site)
    {
        $site->deadlines()->create($request->validate([
            'kind' => ['required', Rule::in(SiteDeadline::KINDS)],
            'name' => ['required', 'string', 'max:190'],
            'due_on' => ['required', 'date'],
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]));

        return back()->with('status', __('ui.saved'));
    }

    /** Odnowiono: termin zamknięty, kolejny za rok. */
    public function renewDeadline(SiteDeadline $deadline)
    {
        $deadline->update(['renewed_at' => now()]);
        $deadline->site->deadlines()->create(['kind' => $deadline->kind, 'name' => $deadline->name, 'due_on' => $deadline->due_on->copy()->addYear(), 'cost' => $deadline->cost]);
        SiteLog::create(['site_id' => $deadline->site_id, 'user_id' => auth()->id(), 'logged_on' => today(), 'kind' => 'other',
            'description' => __('site.renewed_log', ['name' => $deadline->name, 'date' => $deadline->due_on->copy()->addYear()->format('d.m.Y')])]);

        return back()->with('status', __('ui.saved'));
    }

    public function storeLog(Request $request, Site $site)
    {
        $site->logs()->create($request->validate([
            'kind' => ['required', Rule::in(SiteLog::KINDS)],
            'logged_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
        ]) + ['user_id' => $request->user()->id]);

        return back()->with('status', __('ui.saved'));
    }

    public function startReview(Request $request, Site $site)
    {
        $review = $this->sites->scheduleReview($site->forceFill(['next_review_on' => $site->next_review_on ?? today()]), $request->user());

        return redirect()->route('sites.reviews.show', $review);
    }

    public function review(Review $review)
    {
        return view('sites.review', ['review' => $review->load(['site.client', 'ticket'])]);
    }

    public function updateReview(Request $request, Review $review)
    {
        $data = $request->validate(['checked' => ['array'], 'notes' => ['nullable', 'string', 'max:5000'], 'complete' => ['nullable', 'boolean']]);
        $checked = collect($review->items)->keys()->mapWithKeys(fn ($k) => [$k => ! empty($data['checked'][$k])])->all();
        if ($request->boolean('complete')) {
            $this->sites->completeReview($review, $request->user(), $checked, $data['notes'] ?? null);
        } else {
            $review->update(['checked' => $checked, 'notes' => $data['notes'] ?? null]);
        }

        return back()->with('status', __('ui.saved'));
    }

    public function problem(Request $request, Review $review)
    {
        $ticket = $this->sites->problemFromReview($review, $request->user(), $request->validate(['title' => ['required', 'string', 'max:200']])['title']);

        return redirect()->route('tickets.show', $ticket)->with('status', __('site.problem_created'));
    }
}
