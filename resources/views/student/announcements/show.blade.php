@extends('layouts.student')
@section('title', $announcement->subject)
@section('content')

<div style="max-width:780px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a href="{{ route('student.announcements.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.875rem;">← Annunci</a>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:14px;">
            <span style="font-size:0.7rem; color:#E28A53; background:rgba(226,138,83,0.12); padding:2px 8px; border-radius:10px; font-weight:700; letter-spacing:0.05em;">📢 ANNUNCIO</span>
            <span style="font-size:0.7rem; color:#55B1AE; background:rgba(85,177,174,0.1); padding:2px 8px; border-radius:10px; font-weight:600;">{{ $announcement->course->name }}</span>
        </div>

        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">{{ $announcement->subject }}</h2>

        <div style="display:flex; gap:8px; align-items:center; font-size:0.8rem; color:#8A9696; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #F5F7F7;">
            <span>di <strong style="color:#4A5252;">{{ $announcement->instructor->name }}</strong></span>
            <span>•</span>
            <span>{{ $announcement->created_at->format('d/m/Y H:i') }}</span>
        </div>

        <div style="color:#1A1F1F; font-size:0.95rem; line-height:1.6; white-space:pre-wrap; word-wrap:break-word;">{{ $announcement->body }}</div>
    </div>
</div>

@endsection
