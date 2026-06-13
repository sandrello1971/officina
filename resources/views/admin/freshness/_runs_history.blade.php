{{-- Storico analisi (run dell'agente). Reso sia inline nella pagina sia dal polling JS
     (endpoint runs-status) → markup unico, niente duplicazione in JavaScript. --}}
@php($recentRuns = $recentRuns ?? collect())
@php($failedRuns = $recentRuns->where('status', 'failed'))
@if ($recentRuns->isEmpty())
    <div style="color:#8A9696; font-size:0.8rem;">Nessuna analisi ancora. Avvia un controllo con «Lancia ora».</div>
@else
    @if ($failedRuns->isNotEmpty())
    <div style="margin-bottom:8px;">
        <span style="padding:2px 9px; background:#FBEDEC; color:#7B1E1E; border:1px solid #C0392B;
                     border-radius:12px; font-size:0.72rem; font-weight:700;">
            {{ $failedRuns->count() }} fallit{{ $failedRuns->count() == 1 ? 'o' : 'i' }}
        </span>
    </div>
    @endif
    <div style="display:flex; flex-direction:column; gap:6px;">
        @foreach ($recentRuns as $run)
        @php($st = $run->status)
        @php($isFail = $st === 'failed')
        @php($isDone = $st === 'completed')
        <div style="display:flex; align-items:flex-start; gap:10px; padding:8px 10px; border-radius:8px;
                    font-size:0.8rem; line-height:1.45;
                    background:{{ $isFail ? '#FBEDEC' : ($isDone ? 'rgba(85,177,174,0.08)' : '#FFF8EE') }};
                    border:1px solid {{ $isFail ? 'rgba(192,57,43,0.35)' : ($isDone ? 'rgba(85,177,174,0.3)' : 'rgba(226,138,83,0.35)') }};">
            <span style="font-weight:700; white-space:nowrap;
                         color:{{ $isFail ? '#C0392B' : ($isDone ? '#3A8C89' : '#C26A2E') }};">
                {{ $isFail ? '✗ Fallito' : ($isDone ? '✓ Completato' : '⏳ In corso') }}
            </span>
            <div style="flex:1; min-width:0;">
                <strong style="color:#1A1F1F;">{{ optional($run->course)->name ?? '—' }}</strong>
                <span style="color:#8A9696;">· {{ optional($run->created_at)->format('d/m H:i') }}</span>
                @if ($isFail)
                    <div style="color:#7B1E1E; margin-top:2px; font-family:'JetBrains Mono','SF Mono',monospace; font-size:0.74rem;">
                        {{ $run->failure_reason ?: 'Errore non specificato.' }}
                    </div>
                @elseif ($isDone)
                    <span style="color:#8A9696;">· {{ $run->claims_found ?? 0 }} claim, {{ $run->proposals_created ?? 0 }} proposte</span>
                @endif
            </div>
        </div>
        @endforeach
    </div>
@endif
