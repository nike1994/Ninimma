<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Kalendarz miesięczny publikacji (administrator: wszystkie; klient: udostępnione). */
class CalendarController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $month = \App\Support\DateParam::from($request, 'month')->startOfMonth();
        $gridStart = $month->startOfWeek();
        $gridEnd = $month->endOfMonth()->endOfWeek();
        $accountId = $request->integer('account') ?: null;

        $pubs = Publication::visibleTo($user)->with(['content.files.file', 'account'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('scheduled_at', [$gridStart, $gridEnd])
            ->when($accountId, fn ($q) => $q->where('social_account_id', $accountId))
            ->orderBy('scheduled_at')->get()
            ->groupBy(fn ($p) => $p->scheduled_at->toDateString());

        return view('social.calendar', [
            'month' => $month, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'pubs' => $pubs,
            'accounts' => $user->isAdmin() ? SocialAccount::where('is_active', true)->orderBy('name')->get() : collect(),
            'accountId' => $accountId,
            'licFlags' => $user->isAdmin() ? app(\App\Services\LicenseGuard::class)->issuesByPublication() : collect(),
        ]);
    }
}
