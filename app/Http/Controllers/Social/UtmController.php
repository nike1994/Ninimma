<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\UtmLink;
use Illuminate\Http\Request;

class UtmController extends Controller
{
    public function store(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'base_url' => ['required', 'url', 'max:500'],
            'source' => ['required', 'string', 'max:100'],
            'medium' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:150'],
            'term' => ['nullable', 'string', 'max:150'],
        ]);
        $campaign->utmLinks()->create($data + ['full_url' => UtmLink::build($data['base_url'], $data)]);

        return back()->with('status', __('ads.utm_created'));
    }

    public function destroy(UtmLink $link)
    {
        $link->delete();

        return back();
    }
}
