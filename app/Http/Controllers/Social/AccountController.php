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
            'accounts' => SocialAccount::with('client')->withCount('publications')->orderBy('name')->get(),
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

    public function toggle(SocialAccount $account)
    {
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('status', __('ui.saved'));
    }
}
