<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Publication;
use App\Models\Review;
use App\Models\SeoContent;
use App\Models\Site;
use App\Models\SiteDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Wspólny kalendarz: publikacje, treści SEO, przeglądy stron i terminy (domeny, licencje). */
class JointCalendarController extends Controller
{
    public const KINDS = ['publication', 'seo', 'review', 'deadline'];

    public function __invoke(Request $request)
    {
        $month = \App\Support\DateParam::from($request, 'month')->startOfMonth();
        $from = $month->startOfWeek();
        $to = $month->endOfMonth()->endOfWeek();
        $client = $request->integer('client') ?: null;
        $kinds = array_values(array_intersect((array) $request->query('kinds', self::KINDS), self::KINDS)) ?: self::KINDS;
        $items = collect();

        if (in_array('publication', $kinds, true)) {
            Publication::with(['content', 'account'])->where('status', '!=', 'cancelled')->whereBetween('scheduled_at', [$from, $to->endOfDay()])
                ->when($client, fn ($q) => $q->whereHas('account', fn ($a) => $a->where('client_id', $client)))->get()
                ->each(fn ($p) => $items->push(['date' => $p->scheduled_at->toDateString(), 'kind' => 'publication', 'label' => $p->scheduled_at->format('H:i').' '.$p->content->title,
                    'url' => route('social.contents.edit', $p->content_id), 'platform' => $p->platform]));
        }
        if (in_array('seo', $kinds, true)) {
            SeoContent::with(['site', 'ticket'])->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])
                ->when($client, fn ($q) => $q->whereHas('site', fn ($s) => $s->where('client_id', $client)))->get()
                ->each(fn ($c) => $items->push(['date' => $c->due_on->toDateString(), 'kind' => 'seo', 'label' => $c->working_title, 'url' => route('tickets.show', $c->ticket_id)]));
        }
        if (in_array('review', $kinds, true)) {
            Review::with('site')->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])
                ->when($client, fn ($q) => $q->whereHas('site', fn ($s) => $s->where('client_id', $client)))->get()
                ->each(fn ($r) => $items->push(['date' => $r->due_on->toDateString(), 'kind' => 'review', 'label' => __('site.review').' '.$r->site->domain, 'url' => route('sites.reviews.show', $r), 'done' => (bool) $r->completed_at]));
            Site::where('is_active', true)->whereBetween('next_review_on', [$from->toDateString(), $to->toDateString()])
                ->when($client, fn ($q) => $q->where('client_id', $client))->get()
                ->each(fn ($s) => $items->push(['date' => $s->next_review_on->toDateString(), 'kind' => 'review', 'label' => __('site.planned_review').' '.$s->domain, 'url' => route('sites.show', $s)]));
        }
        if (in_array('deadline', $kinds, true)) {
            SiteDeadline::with('site')->whereNull('renewed_at')->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])
                ->when($client, fn ($q) => $q->whereHas('site', fn ($s) => $s->where('client_id', $client)))->get()
                ->each(fn ($d) => $items->push(['date' => $d->due_on->toDateString(), 'kind' => 'deadline', 'label' => __('site.kind_'.$d->kind).': '.$d->site->domain, 'url' => route('sites.show', $d->site_id)]));
        }

        return view('sites.calendar', [
            'month' => $month, 'gridStart' => $from, 'gridEnd' => $to, 'items' => $items->groupBy('date'),
            'clients' => Client::orderBy('name')->get(), 'client' => $client, 'kinds' => $kinds,
        ]);
    }
}
