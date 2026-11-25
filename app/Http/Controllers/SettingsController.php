<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('settings', [
            'colleagues' => $user->client_id ? $user->client->users()->orderBy('name')->get() : collect(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'locale' => ['required', Rule::in(config('agency.locales'))],
            'notify_mode' => ['required', Rule::in(['each', 'daily'])],
        ]);
        $request->user()->update($data);

        return back()->with('status', __('ui.saved'));
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);
        $request->user()->forceFill(['password' => $request->input('password')])->save();

        return back()->with('status', __('ui.saved'));
    }
}
