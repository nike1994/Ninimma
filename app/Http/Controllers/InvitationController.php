<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = Invitation::findValid($token);
        abort_unless($invitation, 404, __('auth.invitation_invalid'));
        app()->setLocale($invitation->locale);

        return view('auth.invitation', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::findValid($token);
        abort_unless($invitation, 404, __('auth.invitation_invalid'));

        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'terms' => [$invitation->role === 'client' ? 'accepted' : 'nullable'],
        ]);

        $user = DB::transaction(function () use ($invitation, $request) {
            $user = User::where('email', $invitation->email)->first() ?? new User(['email' => $invitation->email]);
            $user->fill([
                'name' => $request->input('name'),
                'password' => $request->input('password'),
                'role' => $invitation->role,
                'client_id' => $invitation->role === 'client' ? $invitation->client_id : null,
                'locale' => $invitation->locale,
                'is_active' => true,
            ]);
            $user->email_verified_at = now();
            if ($invitation->role === 'client') {
                $user->terms_accepted_at = now();
                $user->terms_version = \App\Models\Setting::termsVersion();
            }
            $user->save();
            $invitation->forceFill(['accepted_at' => now()])->save();
            // Pozostałe (np. wysłane ponownie) tracą ważność — inaczej stary link przywracałby dezaktywowane konto.
            \App\Models\Invitation::where('email', $invitation->email)->whereNull('accepted_at')->update(['expires_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
