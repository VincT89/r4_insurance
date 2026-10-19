<section wire:poll.30s aria-label="Attività recenti degli utenti">
    <x-panel title="Attività recenti">
        <div class="recent-activity-list">
            @forelse($logs as $log)
                <article class="recent-activity-entry" wire:key="activity-{{ $log->id }}">
                    <div class="recent-activity-meta"><strong>{{ $log->user?->name ?? 'Sistema' }}</strong><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d/m H:i') }}</time></div>
                    <p>{{ $log->description ?: $log->display_action_text }}</p>
                    <a href="{{ route('audit-logs.index',['log_id'=>$log->id]) }}" aria-label="Dettagli attività del {{ $log->created_at->format('d/m/Y H:i') }}">Dettagli</a>
                </article>
            @empty<p class="empty-copy">Nessuna attività registrata.</p>@endforelse
        </div>
        <div class="panel-footer-link"><a href="{{ route('audit-logs.index') }}">Registro completo</a></div>
    </x-panel>
</section>
