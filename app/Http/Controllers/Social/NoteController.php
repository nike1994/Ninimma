<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\SocialNote;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function store(Request $request)
    {
        SocialNote::create($request->validate(['body' => ['required', 'string', 'max:300']]) + ['user_id' => $request->user()->id]);

        return back();
    }

    public function toggle(SocialNote $note)
    {
        $note->update(['is_done' => ! $note->is_done]);

        return back();
    }

    public function destroy(SocialNote $note)
    {
        $note->delete();

        return back();
    }
}
