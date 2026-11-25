<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use App\Services\SocialStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Jeden ekran do wpisania liczb dla wszystkich zaległych publikacji. */
class StatsController extends Controller
{
    public function index()
    {
        return view('social.stats', ['queue' => SocialStats::statsQueue()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'm' => ['array'],
            'm.*.reach' => ['nullable', 'integer', 'min:0'],
            'm.*.reactions' => ['nullable', 'integer', 'min:0'],
            'm.*.comments' => ['nullable', 'integer', 'min:0'],
            'm.*.shares' => ['nullable', 'integer', 'min:0'],
            'm.*.clicks' => ['nullable', 'integer', 'min:0'],
        ])['m'] ?? [];

        $saved = 0;
        DB::transaction(function () use ($data, $request, &$saved) {
            foreach ($data as $pubId => $values) {
                if (collect($values)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                    continue; // puste wiersze pomijamy
                }
                $pub = Publication::findOrFail($pubId);
                $pub->measurements()->create(array_map(fn ($v) => (int) $v, $values) + [
                    'user_id' => $request->user()->id, 'measured_on' => today(),
                ]);
                $pub->increment('checks_done');
                $saved++;
            }
        });

        return redirect()->route('social.stats')->with('status', trans_choice('social.saved_measurements', $saved, ['n' => $saved]));
    }
}
