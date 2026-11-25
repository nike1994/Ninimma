<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientCredential;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kartoteka klienta: dostępy szyfrowane; każde odsłonięcie hasła zapisuje się w dzienniku systemu. */
class CredentialController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $client->credentials()->create($request->validate([
            'label' => ['required', 'string', 'max:160'],
            'url' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'site_id' => ['nullable', Rule::exists('sites', 'id')->where('client_id', $client->id)],
        ]));

        return back()->with('status', __('ui.saved'));
    }

    public function reveal(Request $request, ClientCredential $credential)
    {
        SystemLog::record('credential_reveal', 'Credential revealed', ['credential' => $credential->id, 'client' => $credential->client_id, 'user' => $request->user()->id]);

        return response()->json(['username' => $credential->username, 'secret' => $credential->secret, 'notes' => $credential->notes]);
    }

    public function destroy(ClientCredential $credential)
    {
        $credential->delete();

        return back()->with('status', __('ui.deleted'));
    }

    public function arrangements(Request $request, Client $client)
    {
        $client->update($request->validate(['arrangements' => ['nullable', 'string', 'max:20000']]));

        return back()->with('status', __('ui.saved'));
    }
}
