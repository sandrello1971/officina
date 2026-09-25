@extends($layout)
@section('title', 'Nuova edizione — ' . $course->name)
@section('breadcrumb', 'Nuova edizione')
@section('content')
<div class="ed-wrap" style="max-width:860px;">
    @include('attendance.editions._style')
    <a href="{{ $nav->url('index') }}" class="ed-back">&larr; Edizioni</a>
    <h2 class="ed-h2">Nuova edizione</h2>
    <p class="ed-meta">{{ $course->name }}</p>

    <form method="POST" action="{{ $nav->url('store') }}" data-busy="Creazione delle giornate…">
        @csrf
        <div class="ed-card">
            <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:12px;">Edizione</h3>
            @include('attendance.editions._edition_fields')
        </div>
        <div class="ed-card">
            <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:12px;">Giornate</h3>
            @include('attendance.editions._plan_fields')
            <p style="font-size:0.75rem; color:#8A9696; margin-top:6px;">Dopo la creazione ogni giornata resta modificabile (data, orario, durata) e se ne possono aggiungere o togliere.</p>
        </div>
        <button type="submit" class="ed-btn primary">Crea edizione</button>
    </form>
</div>
@endsection
