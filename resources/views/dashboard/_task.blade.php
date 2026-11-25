<a href="{{ route('tickets.show', $t) }}" @class(['task', 'task-done' => $t->status === \App\Enums\TicketStatus::Done])>
    <span class="task-check">@if($t->status === \App\Enums\TicketStatus::Done)<x-icon name="check" :size="13" />@endif</span>
    <span class="task-body">
        <strong>@if($t->priority->isElevated())<x-icon name="flag" :size="13" class="flag-{{ $t->priority->value }}" /> @endif{{ $t->title }}</strong>
        <span class="task-meta">
            <small><x-icon name="building" :size="13" />{{ $t->client->name }}@if($t->due_on) · <span @class(['overdue' => $t->isOverdue()])>{{ $t->due_on->format('d.m') }}</span>@endif</small>
            @if($t->assignee)<x-avatar :name="$t->assignee->name" :size="24" title="{{ $t->assignee->name }}" />@endif
        </span>
    </span>
</a>
