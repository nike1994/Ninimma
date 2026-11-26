<?php

namespace App\Http\Controllers\Social;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        return view('social.accounts', [
            'accounts' => SocialAccount::with(['client', 'avatar'])->withCount('publications')->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        SocialAccount::create($request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'platform' => ['required', Rule::enum(Platform::class)],
            'name' => ['required', 'string', 'max:120'],
            'handle' => ['nullable', 'string', 'max:120'],
            'url' => ['nullable', 'url', 'max:255'],
        ]));

        return back()->with('status', __('ui.saved'));
    }

    public function avatar(Request $request, SocialAccount $account)
    {
        $request->validate(['avatar' => ['required', 'image', 'max:8192']]);
        if (! $account->storeAvatar((string) file_get_contents($request->file('avatar')->getRealPath()), $request->user()->id)) {
            return back()->withErrors(['avatar' => __('social.avatar_invalid')]);
        }

        return back()->with('status', __('social.avatar_saved'));
    }

    public function toggle(SocialAccount $account)
    {
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('status', __('ui.saved'));
    }
}
