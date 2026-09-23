@extends('layouts.admin')
@section('title', 'Revisione struttura corso — ' . $run->course->name)
@section('content')

<div style="max-width:800px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="{{ route('admin.courses.show', $run->course) }}" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; {{ $run->course->name }}</a>
    </div>

    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Proposta struttura corso</h2>
    <p style="color:#8A9696; font-size:0.85rem; margin:0 0 20px;">
        Rivedi i moduli proposti: modifica titolo/sommario, deseleziona quelli da scartare. All'approvazione
        vengono creati i moduli (bozza, invisibili ai discenti) e parte la generazione di manuale discente,
        manuale formatore e slide per ciascuno.
    </p>

    @if (session('success'))
        <div style="background:rgba(85,177,174,0.12); border:1px solid #55B1AE; color:#1A1F1F; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div style="background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:10px 14px; border-radius:8px; margin-bottom:16px; font-size:0.85rem;">{{ session('error') }}</div>
    @endif

    @if ($run->status === 'running')
        <div id="outline-live-banner" style="display:flex; align-items:center; gap:10px; background:#FFF8EE; border:1px solid rgba(226,138,83,0.45); color:#C26A2E; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:0.85rem; font-weight:700;">
            <span style="display:inline-block; width:12px; height:12px; border:2px solid rgba(226,138,83,0.35); border-top-color:#C26A2E; border-radius:50%; animation:cg-spin 0.8s linear infinite;"></span>
            Generazione della proposta in corso... la pagina si aggiorna da sola.
        </div>
        <style>@keyframes cg-spin { to { transform: rotate(360deg); } }</style>
        <script>
            (function () {
                var poll = setInterval(function () {
                    fetch('{{ route('admin.course-generation.status', $run) }}')
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d.status !== 'running') {
                                clearInterval(poll);
                                window.location.reload();
                            }
                        });
                }, 4000);
            })();
        </script>
    @elseif ($run->status === 'failed')
        <div style="background:#FBEDEC; border:1px solid #C0392B; color:#7B1E1E; padding:14px 16px; border-radius:8px; margin-bottom:20px; font-size:0.85rem;">
            <strong>Generazione fallita:</strong> {{ $run->error }}
        </div>
        <form method="POST" action="{{ route('admin.course-generation.outline.regenerate', $run) }}">
            @csrf
            <button type="submit" style="padding:10px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">Riprova</button>
        </form>
    @elseif ($run->outline)

    <form method="POST" action="{{ route('admin.course-generation.outline.approve', $run) }}">
        @csrf

        <div style="background:white; border-radius:10px; padding:8px; margin-bottom:16px;">
            @foreach ($run->outline as $i => $m)
            <div style="border-bottom:1px solid #F5F7F7; padding:14px; display:flex; gap:12px; align-items:flex-start;">
                <input type="checkbox" name="modules[{{ $i }}][include]" value="1" checked style="margin-top:12px;">
                <div style="flex:1;">
                    <input type="text" name="modules[{{ $i }}][title]" value="{{ $m['title'] }}"
                           style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.9rem; font-weight:700; margin-bottom:6px;">
                    <textarea name="modules[{{ $i }}][summary]" rows="2"
                              style="width:100%; padding:8px; border:1px solid #E8F5F5; border-radius:6px; font-size:0.82rem; color:#4A5252;">{{ $m['summary'] }}</textarea>
                </div>
            </div>
            @endforeach
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end;">
            <button type="submit" formaction="{{ route('admin.course-generation.outline.regenerate', $run) }}" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; background:white; border-radius:8px; font-size:0.85rem; cursor:pointer;">Rigenera proposta</button>
            <button type="submit" data-guard-submit style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                Approva struttura e genera contenuti
            </button>
        </div>
    </form>
    @endif
</div>
@endsection
