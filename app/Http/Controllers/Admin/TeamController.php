<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        return view('admin.team', [
            'admins' => User::where('role', Role::Admin)->orderBy('name')->get(),
            'pending' => Invitation::where('role', Role::Admin->value)->whereNull('accepted_at')->latest()->get(),
        ]);
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'locale' => ['required', Rule::in(config('agency.locales'))],
        ]);
        [$invitation, $token] = Invitation::issue($data + ['role' => Role::Admin->value, 'invited_by' => $request->user()->id]);
        Notification::route('mail', $invitation->email)
            ->notify((new InvitationNotification($token, $invitation->name))->locale($invitation->locale));

        return back()->with('status', __('ui.invitation_sent'));
    }
}
