@extends('layouts.admin')
@section('title', 'Aggiornamenti corsi')
@section('content')

<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
    <h1 style="font-size:1.4rem; color:#1A1F1F; margin:0;">&#128260; Aggiornamenti corsi — coda proposte</h1>
</div>
<p style="color:#8A9696; font-size:0.85rem; margin:0 0 18px;">
    L'agente <strong>propone</strong>; tu <strong>disponi</strong>. Verifica la fonte di ogni proposta
    <em>prima</em> di approvarla: nessuna modifica raggiunge un corso senza la tua approvazione. L'applicazione
    al contenuto avviene in un passo separato.
</p>

@if (session('success'))
    <div style="background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">
        {{ session('success') }}
    </div>
@endif

@php($flatCount = $proposals->flatten()->count())

@if ($flatCount === 0)
    <div style="background:white; border-radius:10px; padding:40px; text-align:center; color:#8A9696;">
        Nessuna proposta in attesa. Quando l'agente troverà contenuti obsoleti, le proposte compariranno qui.
    </div>
@else
    {{-- Form massivo (vuoto): le checkbox e i bottoni vi si agganciano via attributo form= --}}
    <form id="bulkForm" method="POST" action="{{ route('admin.freshness.proposals.bulk') }}">@csrf</form>

    <div style="display:flex; gap:10px; align-items:center; margin-bottom:16px;">
        <button type="submit" form="bulkForm" name="action" value="approve"
                style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">
            &#10003; Approva selezionate
        </button>
        <button type="submit" form="bulkForm" name="action" value="reject"
                style="padding:8px 14px; background:white; color:#C0392B; border:1px solid #C0392B; border-radius:6px; font-size:0.8rem; cursor:pointer;">
            &#10007; Rifiuta selezionate
        </button>
        <span style="color:#8A9696; font-size:0.78rem;">{{ $flatCount }} proposte in attesa</span>
    </div>

    @foreach ($proposals as $courseId => $items)
        @php($course = $items->first()->course)
        @php($audience = optional($course->freshnessConfig)->audience ?? $items->first()->audience)
        <div style="background:white; border-radius:10px; padding:16px 18px; margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px; padding-bottom:10px; border-bottom:1px solid #E6EBEB;">
                <span style="font-family:'JetBrains Mono',monospace; font-weight:700; color:#1A1F1F;">{{ $course->name }}</span>
                @include('admin.freshness._audience_badge', ['audience' => $audience])
                <span style="margin-left:auto; color:#8A9696; font-size:0.78rem;">{{ $items->count() }} proposte</span>
            </div>

            @foreach ($items as $p)
                <div style="border:1px solid #E6EBEB; border-radius:8px; padding:12px 14px; margin-bottom:12px;">
                    {{-- Intestazione riga: selezione + meta + fonte --}}
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                        <input type="checkbox" name="ids[]" value="{{ $p->id }}" form="bulkForm" style="width:16px; height:16px;">
                        <span style="background:#EEF3F3; color:#1A1F1F; font-size:0.7rem; padding:2px 8px; border-radius:10px; text-transform:uppercase; letter-spacing:0.05em;">{{ optional($p->claim)->category ?? '—' }}</span>
                        <code style="color:#8A9696; font-size:0.75rem;">{{ $p->block_id }}@if(!is_null($p->sentence_ref)) · frase {{ $p->sentence_ref }}@endif</code>
                        @include('admin.freshness._audience_badge', ['audience' => $p->audience])
                        <span style="color:#8A9696; font-size:0.75rem;">confidenza: <strong style="color:#1A1F1F;">{{ is_null($p->confidence) ? '—' : round($p->confidence * 100) . '%' }}</strong></span>
                        @if ($p->source)
                            <a href="{{ $p->source }}" target="_blank" rel="noopener noreferrer"
                               style="margin-left:auto; font-size:0.78rem; color:#55B1AE; text-decoration:none; border:1px solid #55B1AE; padding:3px 10px; border-radius:6px;">
                                &#128279; Fonte ({{ $p->source_type ?? 'n/d' }}) &#8599;
                            </a>
                        @else
                            <span style="margin-left:auto; font-size:0.75rem; color:#C0392B;">nessuna fonte</span>
                        @endif
                    </div>

                    {{-- Diff before/after affiancati --}}
                    <div style="display:flex; gap:14px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:260px;">
                            <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:#C0392B; margin-bottom:4px;">Before (attuale)</div>
                            <div style="background:#FBEDEC; border:1px solid #F2C9C4; border-radius:6px; padding:10px; font-size:0.85rem; color:#1A1F1F; white-space:pre-wrap;">{{ $p->before }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.freshness.proposals.approve', $p) }}" style="flex:1; min-width:260px; margin:0;">
                            @csrf @method('PATCH')
                            <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:#1E8449; margin-bottom:4px;">After (proposto — modificabile)</div>
                            <textarea name="after" rows="3" style="width:100%; background:#EDF7F0; border:1px solid #BFE3CC; border-radius:6px; padding:10px; font-size:0.85rem; color:#1A1F1F; box-sizing:border-box; resize:vertical;">{{ $p->after }}</textarea>
                            <button type="submit" style="margin-top:8px; padding:7px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                                &#10003; Approva (con eventuale modifica)
                            </button>
                        </form>
                    </div>

                    @if ($p->reason)
                        <div style="margin-top:10px; font-size:0.8rem; color:#5A6666;"><strong>Motivazione:</strong> {{ $p->reason }}</div>
                    @endif

                    <div style="margin-top:10px; display:flex; gap:8px;">
                        <form method="POST" action="{{ route('admin.freshness.proposals.reject', $p) }}" style="margin:0;">
                            @csrf @method('PATCH')
                            <button type="submit" style="padding:7px 14px; background:white; color:#C0392B; border:1px solid #C0392B; border-radius:6px; font-size:0.8rem; cursor:pointer;">
                                &#10007; Rifiuta
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif

@endsection
