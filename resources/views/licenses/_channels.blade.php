{{-- Kanały użycia licencji. Brak zaznaczeń albo wszystkie = bez ograniczeń. $selected (array|null) --}}
<fieldset class="lic-channel-pick">
    <legend class="small muted">{{ __('lic.channels') }} <span>({{ __('lic.channels_hint') }})</span></legend>
    @foreach(\App\Models\Material::CHANNELS as $ch)
        <label class="lic-ch-check"><input type="checkbox" name="license_channels[]" value="{{ $ch }}" @checked(empty($selected) || in_array($ch, $selected))><span>{{ __('lic.ch_'.$ch) }}</span></label>
    @endforeach
</fieldset>
