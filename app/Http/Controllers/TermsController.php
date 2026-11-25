<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

/** Regulamin współpracy: akceptowany przy aktywacji konta; nowa wersja wymaga ponownej akceptacji. */
class TermsController extends Controller
{
    public function show(Request $request)
    {
        return view('terms.show', ['terms' => Setting::get('terms', ''), 'version' => Setting::termsVersion()]);
    }

    public function accept(Request $request)
    {
        $request->validate(['accept' => ['accepted']]);
        $request->user()->forceFill(['terms_accepted_at' => now(), 'terms_version' => Setting::termsVersion()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function edit()
    {
        return view('terms.edit', ['terms' => Setting::get('terms', ''), 'version' => Setting::termsVersion()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['terms' => ['required', 'string', 'max:50000'], 'new_version' => ['nullable', 'boolean']]);
        Setting::put('terms', $data['terms']);
        if ($request->boolean('new_version') || Setting::termsVersion() === 0) {
            Setting::put('terms_version', Setting::termsVersion() + 1);
        }

        return back()->with('status', __('ui.saved'));
    }
}
