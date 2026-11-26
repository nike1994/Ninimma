<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Services\AdService;
use App\Services\LicenseGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Przenoszenie publikacji w kalendarzu (przeciągnij i upuść). */
class PublicationController extends Controller
{
    public function reschedule(Request $request, Publication $publication)
    {
        $data = $request->validate(['at' => ['required', 'date_format:Y-m-d H:i']]);
        if ($publication->status !== 'planned') {
            return response()->json(['message' => __('social.move_only_planned')], 422);
        }
        $at = Carbon::createFromFormat('Y-m-d H:i', $data['at']);
        $publication->loadMissing('content.materials');

        // Te same zasady co w formularzu: licencje materiałów i okres promocji.
        $errors = LicenseGuard::publishBlockers($publication->content->materials, $at);
        $shift = (int) $publication->scheduled_at->copy()->startOfDay()->diffInDays($at->copy()->startOfDay(), false);
        $promoFrom = $publication->promo_from?->copy()->addDays($shift);
        $promoTo = $publication->promo_to?->copy()->addDays($shift);
        if ($publication->promoted) {
            $errors = array_merge($errors, LicenseGuard::promoteBlockers($publication->content->materials, $promoFrom ?? $at, $promoTo));
        }
        if ($errors) {
            return response()->json(['message' => implode(' ', $errors)], 422);
        }

        $publication->forceFill(['scheduled_at' => $at, 'promo_from' => $promoFrom, 'promo_to' => $promoTo]);
        // Nowy termin = nowa szansa dla automatycznej publikacji.
        if ($publication->api_status !== 'processing') {
            $publication->forceFill(['api_status' => null, 'api_error' => null, 'api_attempts' => 0, 'api_next_attempt_at' => null, 'api_container' => null]);
        }
        $publication->save();
        app(AdService::class)->syncFromPublication($publication);

        return response()->json([
            'ok' => true,
            'date' => $at->toDateString(),
            'time' => $at->format('H:i'),
            'message' => __('social.moved', ['date' => $at->translatedFormat('j F, H:i')]),
        ]);
    }
}
