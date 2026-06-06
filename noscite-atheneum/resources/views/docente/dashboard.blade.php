@extends('layouts.docente')
@section('title', 'Dashboard docente')
@section('breadcrumb', 'Dashboard')
@section('content')

<div style="max-width:900px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">
        Benvenuto, {{ session('student_name') }}
    </h1>
    <p style="color:#4A5252; font-size:0.9rem; margin-bottom:24px;">
        Questa è l'area docente di Schola. Le sezioni <strong>Classi</strong>,
        <strong>Materiali</strong> e <strong>Biblioteca</strong> arriveranno con i
        prossimi pacchetti.
    </p>

    <div style="background:white; border-radius:10px; padding:24px; border:1px solid #C8D0D0;">
        <div style="color:#8A9696; font-size:0.8rem;">
            Scheletro area docente attivo. Nessun contenuto operativo in questo pacchetto.
        </div>
    </div>
</div>

@endsection
