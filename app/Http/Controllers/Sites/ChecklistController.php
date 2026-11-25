<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChecklistController extends Controller
{
    public function index()
    {
        return view('sites.checklists', ['checklists' => Checklist::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        Checklist::create($this->validated($request));

        return back()->with('status', __('ui.saved'));
    }

    public function update(Request $request, Checklist $checklist)
    {
        $checklist->update($this->validated($request));

        return back()->with('status', __('ui.saved'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'platform' => ['nullable', Rule::in(Site::PLATFORMS)],
            'items' => ['required', 'string', 'max:5000'],
        ]);
        $data['items'] = collect(preg_split('/\R/', $data['items']))->map(fn ($l) => trim(ltrim(trim($l), '-•*')))->filter()->values()->all();

        return $data;
    }
}
