{{-- Wybór materiałów influencerów/UGC z licznikiem licencji. $materials, $selected (ids), $name, $multiple --}}
@php($multiple = $multiple ?? true)
<div class="mat-picker" data-mat-picker>
    @forelse($materials as $m)
        @php($st = \App\Services\LicenseGuard::status($m))
        @php($locked = $st->state === 'expired' && ! in_array($m->id, $selected))
        <label @class(['mat-opt', 'lic-'.$st->state, 'locked' => $locked]) data-client="{{ $m->collaboration->campaign->client_id }}" @if($locked) title="{{ __('lic.picker_expired') }}" @endif>
            <input type="{{ $multiple ? 'checkbox' : 'radio' }}" name="{{ $name }}" value="{{ $m->id }}" @checked(in_array($m->id, $selected)) @disabled($locked)>
            <x-material-thumb :material="$m" :size="56" />
            <span class="mat-opt-body">
                <strong>{{ $m->description }}</strong>
                <small class="muted">{{ $m->collaboration->influencer->name }} · {{ $m->collaboration->campaign->name }}</small>
                @if($st->state === 'expired')<small class="error">{{ __('lic.picker_expired') }}</small>@endif
                <small class="lic-channels">@foreach(\App\Models\Material::CHANNELS as $ch)<span @class(['lic-ch', 'off' => ! $m->allows($ch)])>{{ __('lic.ch_'.$ch) }}</span>@endforeach</small>
            </span>
            <x-license-ring :material="$m" :size="38" />
        </label>
    @empty
        <p class="muted small">{{ __('lic.no_materials') }}</p>
    @endforelse
    @unless($multiple)
        <label class="mat-opt"><input type="radio" name="{{ $name }}" value="" @checked(empty($selected))><span class="mat-opt-body"><strong>{{ __('lic.no_material') }}</strong></span></label>
    @endunless
</div>
