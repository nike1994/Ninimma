@props(['ticket'])
<span class="badge status-{{ $ticket->status->value }}">{{ $ticket->status->label() }}</span>
