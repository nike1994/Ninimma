<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use Illuminate\Http\Request;

/** Klient: udostępnione publikacje z podglądem grafik i opisów; akceptacja lub komentarz. */
class ClientPublicationController extends Controller
{
    public function index(Request $request)
    {
        return view('social.client', [
            'pubs' => Publication::visibleTo($request->user())->with(['content.files.file', 'account', 'latestMeasurement'])
                ->where('status', '!=', 'cancelled')->orderByDesc('scheduled_at')->paginate(20),
        ]);
    }

    public function respond(Request $request, Publication $publication)
    {
        abort_unless(Publication::visibleTo($request->user())->whereKey($publication->id)->exists(), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,changes'],
            'comment' => ['nullable', 'required_if:decision,changes', 'string', 'max:2000'],
        ]);
        $publication->forceFill(['client_status' => $data['decision'], 'client_comment' => $data['comment'] ?? null])->save();

        return back()->with('status', __('ui.saved'));
    }
}
