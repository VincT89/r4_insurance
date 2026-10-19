@props([
    'logs' => [],
    'title' => 'Attività recenti',
])

@can('system.admin')
    <div class="audit-timeline-container">
        <x-panel :title="$title" padded>
            @if(count($logs))
                <div class="audit-timeline-list">
                    @foreach($logs as $log)
                        <article class="audit-timeline-item">
                            <div class="audit-timeline-dot" aria-hidden="true"></div>
                            <div class="audit-timeline-content">
                                <div class="audit-timeline-heading">
                                    <span class="audit-timeline-action">{{ $log->display_action_label }}</span>
                                    <time
                                        class="audit-timeline-date"
                                        datetime="{{ $log->created_at->toIso8601String() }}"
                                        title="{{ $log->created_at->locale('it')->translatedFormat('d F Y, H:i') }}"
                                    >
                                        {{ $log->created_at->locale('it')->diffForHumans() }}
                                    </time>
                                </div>
                                <div class="audit-timeline-text">
                                    <strong>{{ $log->user?->name ?? 'Sistema' }}</strong>
                                    {{ $log->display_action_text }}
                                </div>
                                @if($log->description)<p class="u-text-meta">{{ $log->description }}</p>@endif
                                @if($changes = \App\Support\AuditChanges::rows($log))
                                    <details class="u-mt-sm">
                                        <summary>Dettagli dell’operazione</summary>
                                        <dl class="client-registry-details">
                                            @foreach($changes as $change)
                                                <div><dt>{{ $change['label'] }}</dt><dd>@if($log->old_values)<span class="u-text-meta">Prima: {{ $change['before'] }}</span><br>@endif{{ $change['after'] }}</dd></div>
                                            @endforeach
                                        </dl>
                                    </details>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="audit-timeline-empty">
                    Nessuna attività registrata per i criteri selezionati.
                </div>
            @endif
        </x-panel>
    </div>
@endcan
