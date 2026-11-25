<section class="card">
    <h2><x-icon name="bell" /> {{ __('inf.reminders') }}</h2>
    @foreach($reminders as $r)
        <form method="post" action="{{ route('reminders.toggle', $r) }}" @class(['todo-row', 'done' => $r->is_done])>@csrf @method('patch')
            <button class="todo-box">@if($r->is_done)<x-icon name="check" :size="13" />@endif</button>
            <span class="todo-text">{{ $r->body }}</span>
            <span @class(['todo-date', 'overdue' => ! $r->is_done && $r->due_on->lte(today())])><x-icon name="calendar" :size="14" />{{ $r->due_on->format('d.m') }}</span>
        </form>
    @endforeach
    <form method="post" action="{{ route('reminders.store') }}" class="reminder-add">
        @csrf<input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="id" value="{{ $id }}">
        <input name="body" placeholder="{{ __('inf.reminder_placeholder') }}" required maxlength="255">
        <input type="date" name="due_on" value="{{ today()->addDays(3)->toDateString() }}" required>
        <button class="icon-btn icon-btn-sm"><x-icon name="plus" :size="16" /></button>
    </form>
</section>
