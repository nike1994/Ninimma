<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Material;
use App\Services\LicenseGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Ekran licencji: liczniki, oś czasu, konflikty z planem publikacji i reklamami, przedłużenia. */
class LicenseController extends Controller
{
    public function __construct(private LicenseGuard $guard) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $clientId = $request->integer('client') ?: null;
        $materials = Material::with(['collaboration.influencer.photo', 'collaboration.campaign.client', 'file.file', 'contents.publications', 'ads', 'extensions.user'])
            ->when($clientId, fn ($q) => $q->whereHas('collaboration.campaign', fn ($c) => $c->where('client_id', $clientId)))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('description', 'like', "%{$t}%")
                ->orWhereHas('collaboration.influencer', fn ($i) => $i->where('name', 'like', "%{$t}%"))))
            ->get();

        $issues = $this->guard->allIssues($clientId);
        $withIssues = $issues->pluck('material.id')->unique();
        $materials = $materials->each(fn ($m) => $m->setAttribute('lic', LicenseGuard::status($m)))
            ->filter(fn ($m) => match ($filter) {
                'expiring' => $m->lic->state === 'expiring',
                'expired' => $m->lic->state === 'expired',
                'active' => in_array($m->lic->state, ['active', 'upcoming'], true),
                'conflicts' => $withIssues->contains($m->id),
                'none' => $m->lic->state === 'none',
                default => true,
            })
            // Najpierw najbliższe końca, potem wygasłe, na końcu bez daty.
            ->sortBy(fn ($m) => match ($m->lic->state) {
                'expiring' => [0, $m->lic->days_left], 'active', 'upcoming' => [1, $m->lic->days_left], 'expired' => [2, -$m->lic->days_left], default => [3, 0],
            })->values();

        $all = Material::whereNotNull('license_until')->get(['id', 'license_from', 'license_until', 'created_at'])->map(fn ($m) => LicenseGuard::status($m)->state);

        // Oś czasu: od dziś −30 dni do +150 dni.
        $axisStart = today()->subDays(30);
        $axisEnd = today()->addDays(150);

        return view('licenses.index', [
            'materials' => $materials,
            'issues' => $issues,
            'filter' => $filter,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'counts' => [
                'active' => $all->filter(fn ($s) => in_array($s, ['active', 'upcoming'], true))->count(),
                'expiring' => $all->filter(fn ($s) => $s === 'expiring')->count(),
                'expired' => $all->filter(fn ($s) => $s === 'expired')->count(),
                'conflicts' => $issues->where('severity', 'error')->count(),
            ],
            'axisStart' => $axisStart,
            'axisEnd' => $axisEnd,
            'axisDays' => $axisStart->diffInDays($axisEnd),
        ]);
    }

    /** Zmiana warunków licencji materiału (daty, kanały, zdjęcie postów). */
    public function update(Request $request, Material $material)
    {
        $data = $request->validate([
            'license_from' => ['nullable', 'date'],
            'license_until' => ['nullable', 'date', 'after_or_equal:license_from'],
            'license_channels' => ['nullable', 'array'],
            'license_channels.*' => [Rule::in(Material::CHANNELS)],
            'requires_takedown' => ['nullable', 'boolean'],
            'kind' => ['nullable', Rule::in(Material::KINDS)],
        ]);
        $material->update([
            'license_from' => $data['license_from'] ?? null,
            'license_until' => $data['license_until'] ?? null,
            'license_channels' => self::channels($data['license_channels'] ?? []),
            'requires_takedown' => $request->boolean('requires_takedown'),
        ] + (isset($data['kind']) ? ['kind' => $data['kind']] : []));

        return back()->with('status', __('ui.saved'));
    }

    public function extend(Request $request, Material $material)
    {
        $data = $request->validate([
            'new_until' => ['required', 'date', 'after:today'],
            'license_channels' => ['nullable', 'array'],
            'license_channels.*' => [Rule::in(Material::CHANNELS)],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->guard->extend($material, $request->user(), $data['new_until'],
            $request->has('license_channels') ? self::channels($data['license_channels'] ?? []) : null, $data['cost'] ?? null, $data['note'] ?? null);

        return back()->with('status', __('lic.extended', ['date' => \Illuminate\Support\Carbon::parse($data['new_until'])->format('d.m.Y')]));
    }

    /** Wszystkie kanały zaznaczone = brak ograniczeń (null). */
    public static function channels(array $channels): ?array
    {
        $channels = array_values(array_intersect(Material::CHANNELS, $channels));

        return count($channels) === count(Material::CHANNELS) || $channels === [] ? null : $channels;
    }
}
