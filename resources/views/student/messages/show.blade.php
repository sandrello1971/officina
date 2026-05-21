@extends('layouts.student')
@section('title', $conversation->subject)
@section('content')

@php
    $currentUser = \App\Models\Student::find(session('student_id'));
    $other = $conversation->otherParticipant($currentUser);
@endphp

<div style="max-width:780px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a href="{{ route('student.messages.index') }}" style="color:#8A9696; text-decoration:none; font-size:0.875rem;">← Messaggi</a>
    </div>

    {{-- Header thread --}}
    <div style="background:white; border-radius:10px 10px 0 0; padding:18px 22px; border-bottom:1px solid #F5F7F7;">
        <h2 style="font-size:1.1rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">{{ $conversation->subject }}</h2>
        <div style="display:flex; gap:12px; align-items:center; font-size:0.8rem; color:#8A9696;">
            <span>con <strong style="color:#4A5252;">{{ $other?->name ?? '—' }}</strong></span>
            @if($conversation->course)
            <span>•</span>
            <span style="color:#55B1AE; font-weight:600;">{{ $conversation->course->name }}</span>
            @endif
        </div>
    </div>

    {{-- Lista messaggi --}}
    <div style="background:#FAFBFB; padding:20px 22px; max-height:540px; overflow-y:auto;">
        @foreach($conversation->messages as $msg)
            @php $isMine = $msg->sender_id === $currentUser->id; @endphp
            <div style="display:flex; justify-content:{{ $isMine ? 'flex-end' : 'flex-start' }}; margin-bottom:14px;">
                <div style="max-width:75%;">
                    <div style="font-size:0.7rem; color:#8A9696; margin-bottom:3px; {{ $isMine ? 'text-align:right;' : '' }}">
                        <strong style="color:#4A5252;">{{ $isMine ? 'Tu' : $msg->sender->name }}</strong>
                        · {{ $msg->created_at->format('d/m/Y H:i') }}
                    </div>
                    <div style="background:{{ $isMine ? '#55B1AE' : 'white' }}; color:{{ $isMine ? 'white' : '#1A1F1F' }}; padding:10px 14px; border-radius:12px; font-size:0.9rem; line-height:1.45; white-space:pre-wrap; word-wrap:break-word; box-shadow:0 1px 2px rgba(0,0,0,0.04);">{{ $msg->body }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Form reply --}}
    <div style="background:white; border-radius:0 0 10px 10px; padding:16px 22px;">
        @if($errors->any())
        <div style="background:#FBE9E7; border-left:4px solid #C52A2A; padding:10px 14px; border-radius:6px; margin-bottom:12px; color:#C52A2A; font-size:0.85rem;">
            {{ $errors->first() }}
        </div>
        @endif

        <form method="POST" action="{{ route('student.messages.messages.store', $conversation) }}">
            @csrf
            <textarea name="body" rows="3" maxlength="5000" required
                      placeholder="Scrivi la tua risposta…"
                      style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; resize:vertical; min-height:70px;">{{ old('body') }}</textarea>
            <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                <button type="submit" style="padding:8px 20px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:600; cursor:pointer;">Invia</button>
            </div>
        </form>
    </div>
</div>

@endsection
