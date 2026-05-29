@extends('layouts.admin')
@section('title', $course->name . ' — Mappe concettuali')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <a href="/admin/courses" style="color:#8A9696; font-size:0.8rem;">&larr; Corsi</a>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-top:4px;">
            {{ $course->icon }} {{ $course->name }} — Mappe concettuali
        </h2>
        <p style="font-size:0.85rem; color:#8A9696; margin-top:6px; max-width:680px;">
            Crea grafi di concetti con relazioni etichettate (à la Novak/Cmap). Multiple mappe per corso. Gli studenti
            possono forkare e personalizzare la loro versione di ogni mappa <em>published</em>.
        </p>
    </div>
    <a href="/admin/courses/{{ $course->id }}/concept-maps/create"
       style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
        + Nuova mappa concettuale
    </a>
</div>

@if(session('success'))
    <div style="padding:10px 14px; background:#D1FAE5; color:#059669; border-radius:6px; margin-bottom:14px; font-size:0.875rem;">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div style="padding:10px 14px; background:#FEE2E2; color:#991B1B; border-radius:6px; margin-bottom:14px; font-size:0.875rem;">{{ session('error') }}</div>
@endif

<div style="display:flex; flex-direction:column; gap:8px;">
    @forelse($maps as $map)
        <div style="background:white; border-radius:10px; padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
            <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="font-weight:600; color:#1A1F1F;">{{ $map->title }}</div>
                    @if($map->isPublished())
                        <span style="font-size:0.7rem; padding:2px 8px; background:#D1FAE5; color:#059669; border-radius:4px; font-weight:600;">PUBLISHED</span>
                    @else
                        <span style="font-size:0.7rem; padding:2px 8px; background:#F3F4F6; color:#6B7280; border-radius:4px; font-weight:600;">DRAFT</span>
                    @endif
                    @if($map->ai_generated)
                        <span style="font-size:0.7rem; padding:2px 8px; background:#E8F5F5; color:#3D8B88; border-radius:4px; font-weight:600;">AI-GENERATED</span>
                    @endif
                    @if($map->ai_generated && $map->isStale())
                        <span style="font-size:0.7rem; padding:2px 8px; background:#FEF3C7; color:#92400E; border-radius:4px; font-weight:600;">&#9888; OBSOLETA</span>
                    @endif
                </div>
                @if($map->description)
                    <div style="font-size:0.8rem; color:#4A5252; margin-top:4px;">{{ \Illuminate\Support\Str::limit($map->description, 160) }}</div>
                @endif
                <div style="font-size:0.75rem; color:#8A9696; margin-top:4px;">
                    {{ count($map->data['nodes'] ?? []) }} concetti &middot; {{ count($map->data['edges'] ?? []) }} relazioni
                    @if($map->ai_generated_at) &middot; generata {{ $map->ai_generated_at->diffForHumans() }} @endif
                </div>
            </div>
            <div style="display:flex; gap:8px;">
                <a href="/admin/courses/{{ $course->id }}/concept-maps/{{ $map->id }}/edit"
                   style="padding:6px 14px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                    Editor
                </a>
                <form action="/admin/courses/{{ $course->id }}/concept-maps/{{ $map->id }}" method="POST"
                      onsubmit="return confirm('Eliminare la mappa concettuale &quot;{{ $map->title }}&quot;? Anche i fork degli studenti verranno eliminati.');">
                    @csrf @method('DELETE')
                    <button type="submit" style="padding:6px 14px; background:white; color:#991B1B; border:1px solid #991B1B; border-radius:6px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                        Elimina
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div style="background:white; border-radius:10px; padding:30px; text-align:center; color:#8A9696;">
            Nessuna mappa concettuale per questo corso. Clicca <strong>+ Nuova mappa concettuale</strong> per iniziare.
        </div>
    @endforelse
</div>
@endsection
