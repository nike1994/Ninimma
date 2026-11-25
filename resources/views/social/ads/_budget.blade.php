{{-- Pasek wykorzystania budżetu: $spent, $budget --}}
@php
    $pct = $budget > 0 ? (int) round($spent / $budget * 100) : null;
@endphp
<div class="budget">
    <div @class(['limit-bar', 'warn' => $pct !== null && $pct >= 90, 'over' => $pct !== null && $pct > 100])><span style="width: {{ min(100, $pct ?? 0) }}%"></span></div>
    <small class="muted">{{ number_format($spent, 0, ',', ' ') }} / {{ number_format($budget, 0, ',', ' ') }} zł @if($pct !== null)· {{ $pct }}%@endif</small>
</div>
