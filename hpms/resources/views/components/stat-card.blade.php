@props(['label', 'value', 'note' => null])
<div class="stat-card"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div>@if($note)<div class="stat-note">{{ $note }}</div>@endif</div>
