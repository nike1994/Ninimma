{{-- Pasek etapów współpracy. --}}
@php($idx = $c->stageIndex())
<div class="stages" title="{{ __('inf.stage_'.$c->stage()) }}">
    @foreach(\App\Models\Collaboration::STAGES as $i => $st)
        <span @class(['done' => $i < $idx, 'now' => $i === $idx, 'skip' => $st === 'shipment' && ! $c->needs_shipment])></span>
    @endforeach
</div>
