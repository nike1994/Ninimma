<?php

namespace App\Http\Controllers\Influencers;

use App\Http\Controllers\Controller;
use App\Models\Collaboration;
use App\Models\FileUsage;
use App\Models\Material;
use App\Models\Publication;
use App\Models\Reminder;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollaborationController extends Controller
{
    /** Pola dat etapów, które można odhaczać. */
    private const STEPS = ['contract_sent', 'contract_signed', 'brief_sent', 'brief_accepted', 'materials_complete', 'published', 'paid'];

    public function store(Request $request)
    {
        $data = $this->validated($request) + $request->validate([
            'influencer_id' => ['required', 'exists:influencers,id'],
            'campaign_id' => ['required', 'exists:campaigns,id'],
        ]);
        $collab = Collaboration::create($data);
        $collab->forceFill(['influencer_id' => $data['influencer_id'], 'campaign_id' => $data['campaign_id']])->save();

        return redirect()->route('collaborations.show', $collab)->with('status', __('ui.saved'));
    }

    public function show(Collaboration $collaboration)
    {
        return view('influencers.collaboration', [
            'c' => $collaboration->load(['influencer.photo', 'campaign.client', 'shipments', 'materials.file.file', 'materials.publication.account', 'contract',
                'reminders' => fn ($q) => $q->orderBy('is_done')->orderBy('due_on')]),
            'publications' => Publication::with(['content', 'account'])->whereHas('account', fn ($q) => $q->where('client_id', $collaboration->campaign->client_id))
                ->latest('scheduled_at')->limit(50)->get(),
        ]);
    }

    public function update(Request $request, Collaboration $collaboration)
    {
        $collaboration->update($this->validated($request));
        $this->attachUpload($request, fn (FileUsage $u) => $collaboration->update(['contract_usage_id' => $u->id]), 'collaboration', $collaboration->id);

        return back()->with('status', __('ui.saved'));
    }

    /** Odhaczenie / cofnięcie etapu (zapisuje datę). */
    public function step(Request $request, Collaboration $collaboration)
    {
        $step = $request->validate(['step' => ['required', Rule::in(self::STEPS)]])['step'];
        $col = $step.'_at';
        $collaboration->forceFill([$col => $collaboration->{$col} ? null : now()])->save();
        if ($collaboration->stage() === 'done' && $collaboration->status === 'active') {
            // Zakończona współpraca: notatki prywatne są usuwane.
            $collaboration->forceFill(['status' => 'done', 'private_notes' => null])->save();
        } elseif ($collaboration->status === 'done' && $collaboration->stage() !== 'done') {
            $collaboration->forceFill(['status' => 'active'])->save();
        }

        return back();
    }

    public function cancel(Request $request, Collaboration $collaboration)
    {
        $reason = $request->validate(['cancel_reason' => ['required', 'string', 'max:250']])['cancel_reason'];
        $collaboration->forceFill(['status' => 'cancelled', 'cancel_reason' => $reason, 'private_notes' => null])->save();

        return back()->with('status', __('ui.saved'));
    }

    public function storeShipment(Request $request, Collaboration $collaboration)
    {
        $collaboration->shipments()->create($request->validate([
            'contents' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'sent_on' => ['nullable', 'date'],
        ]));

        return back();
    }

    public function updateShipment(Request $request, Shipment $shipment)
    {
        $data = $request->validate(['field' => ['required', Rule::in(['sent_on', 'delivered_on'])]]);
        $shipment->update([$data['field'] => $shipment->{$data['field']} ? null : today()]);

        return back();
    }

    public function storeMaterial(Request $request, Collaboration $collaboration)
    {
        $data = $request->validate(['description' => ['required', 'string', 'max:255']]);
        $collaboration->materials()->create($data + ['license_until' => $collaboration->license_to]);

        return back();
    }

    public function updateMaterial(Request $request, Material $material)
    {
        $data = $request->validate([
            'is_received' => ['nullable', 'boolean'],
            'publication_id' => ['nullable', 'exists:publications,id'],
            'license_until' => ['nullable', 'date'],
        ]);
        $material->update(['is_received' => $request->boolean('is_received'), 'publication_id' => $data['publication_id'] ?? null,
            'license_until' => $data['license_until'] ?? null]);
        $this->attachUpload($request, function (FileUsage $u) use ($material) {
            $material->update(['file_usage_id' => $u->id, 'is_received' => true]);
        }, 'material', $material->id);
        $c = $material->collaboration;
        if ($c->materials()->count() && $c->materials()->where('is_received', false)->doesntExist() && ! $c->materials_complete_at) {
            $c->forceFill(['materials_complete_at' => now()])->save();
        }

        return back();
    }

    public function storeReminder(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['influencer', 'collaboration', 'ticket'])],
            'id' => ['required', 'integer'],
            'due_on' => ['required', 'date'],
            'body' => ['required', 'string', 'max:255'],
        ]);
        Reminder::create(['remindable_type' => $data['type'], 'remindable_id' => $data['id'], 'due_on' => $data['due_on'],
            'body' => $data['body'], 'user_id' => $request->user()->id]);

        return back();
    }

    public function toggleReminder(Reminder $reminder)
    {
        $reminder->update(['is_done' => ! $reminder->is_done]);

        return back();
    }

    private function attachUpload(Request $request, callable $then, string $type, int $id): void
    {
        $uid = $request->input('uploads.0');
        $usage = $uid ? FileUsage::where('id', $uid)->where('uploaded_by', $request->user()->id)->whereNull('usable_id')->first() : null;
        if ($usage) {
            $usage->update(['usable_type' => $type, 'usable_id' => $id]);
            $then($usage);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'rate' => ['required', 'numeric', 'min:0'],
            'billing' => ['required', Rule::in(['contract', 'invoice'])],
            'scope' => ['nullable', 'string', 'max:2000'],
            'license_scope' => ['nullable', 'string', 'max:255'],
            'license_from' => ['nullable', 'date'],
            'license_to' => ['nullable', 'date', 'after_or_equal:license_from'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
            'tracking_link' => ['nullable', 'url', 'max:255'],
            'discount_code' => ['nullable', 'string', 'max:64'],
            'sales' => ['nullable', 'integer', 'min:0'],
            'revenue' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $data + ['needs_shipment' => $request->boolean('needs_shipment')];
    }
}
