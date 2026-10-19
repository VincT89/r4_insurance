@props(['log'])

@can('system.admin')
<div class="audit-item">
    <span class="audit-time">{{ $log->created_at->format('H:i:s') }}</span>
    <div
        class="audit-icon bg-{{ str_replace('_', '-', $log->action) }}"
        aria-hidden="true"
    ></div>
    <div class="audit-body">
        <b>{{ $log->user?->name ?? 'Sistema' }}</b>
        {{ $log->display_action_text }}
        @if($log->description)<p>{{ $log->description }}</p>@endif
        @php
            $before = \Illuminate\Support\Arr::dot($log->old_values ?? []);
            $after = \Illuminate\Support\Arr::dot($log->new_values ?? []);
            $fields = array_unique(array_merge(array_keys($before),array_keys($after)));
        @endphp
        @if($fields)<details class="audit-changes"><summary>Dettaglio delle modifiche</summary><dl class="audit-change-grid">
            @foreach($fields as $field)
                @unless(preg_match('/password|token|secret/i',$field))
                <div><dt>{{ str_replace('_',' ',$field) }}</dt><dd>Prima: {{ is_scalar($before[$field] ?? null) ? (string)$before[$field] : 'Non indicato' }}<br>Dopo: {{ is_scalar($after[$field] ?? null) ? (string)$after[$field] : 'Non indicato' }}</dd></div>
                @endunless
            @endforeach
        </dl></details>@endif
        <div class="audit-foot">
            {{ $log->display_action_label }} · {{ $log->created_at->locale('it')->diffForHumans() }}
        </div>
    </div>
</div>
@endcan
