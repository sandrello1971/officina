@extends('layouts.admin')
@section('title', 'Corsi')
@section('content')

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Gestione Corsi</h2>
    <div style="display:flex; gap:8px;">
        <a href="/admin/courses/ingest" style="padding:8px 18px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">📥 Crea da documenti</a>
        <a href="/admin/courses/create" style="padding:8px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">+ Nuovo corso</a>
    </div>
</div>

{{-- Barra filtri: categoria + tag (AND) + ricerca testuale, combinabili --}}
<form method="GET" action="/admin/courses" style="background:white; border-radius:10px; padding:16px; margin-bottom:16px; display:grid; gap:12px;">
    <div style="display:grid; grid-template-columns:1fr 2fr auto auto; gap:12px; align-items:end;">
        <div>
            <label style="font-size:0.72rem; font-weight:600; color:#8A9696; text-transform:uppercase; display:block; margin-bottom:6px;">Categoria</label>
            <select name="category" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none; background:white;">
                <option value="">Tutte</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ (string) $categoryId === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:0.72rem; font-weight:600; color:#8A9696; text-transform:uppercase; display:block; margin-bottom:6px;">Ricerca (nome o descrizione)</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Cerca…"
                   style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
        </div>
        <button type="submit" style="padding:9px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">Filtra</button>
        <a href="/admin/courses" style="padding:9px 16px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.85rem; text-decoration:none;">Azzera</a>
    </div>
    @if($tags->isNotEmpty())
    <div>
        <label style="font-size:0.72rem; font-weight:600; color:#8A9696; text-transform:uppercase; display:block; margin-bottom:6px;">Tag (il corso deve averli tutti)</label>
        <div style="display:flex; flex-wrap:wrap; gap:8px;">
            @foreach($tags as $tag)
                <label style="display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border:1px solid #C8D0D0; border-radius:16px; font-size:0.8rem; color:#4A5252; cursor:pointer; background:{{ in_array((string) $tag->id, array_map('strval', $tagIds), true) ? 'rgba(85,177,174,0.12)' : 'white' }};">
                    <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ in_array((string) $tag->id, array_map('strval', $tagIds), true) ? 'checked' : '' }}>
                    {{ $tag->name }}
                </label>
            @endforeach
        </div>
    </div>
    @endif
</form>

<div style="background:white; border-radius:10px; overflow:hidden;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#F5F7F7;">
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Corso</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Categoria / Tag</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Moduli</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Ore</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Stato</th>
                <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            @foreach($courses as $course)
            <tr style="border-bottom:1px solid #F5F7F7;">
                <td style="padding:12px 16px;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:1.2rem;">{{ $course->icon }}</span>
                        <div>
                            <div style="font-weight:600; color:#1A1F1F; font-size:0.875rem;">{{ $course->name }}</div>
                            <div style="color:#8A9696; font-size:0.75rem;">{{ $course->short_description }}</div>
                        </div>
                    </div>
                </td>
                <td style="padding:12px 16px;">
                    @if($course->category)
                        <span style="display:inline-block; padding:3px 8px; border-radius:4px; font-size:0.72rem; font-weight:600; color:white; background:{{ $course->category->color ?? '#55B1AE' }};">{{ $course->category->name }}</span>
                    @else
                        <span style="color:#C8D0D0; font-size:0.75rem;">—</span>
                    @endif
                    @if($course->tags->isNotEmpty())
                        <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;">
                            @foreach($course->tags as $tag)
                                <span style="padding:2px 7px; border-radius:12px; font-size:0.68rem; background:#F5F7F7; color:#8A9696; border:1px solid #E5E9E9;">{{ $tag->name }}</span>
                            @endforeach
                        </div>
                    @endif
                </td>
                <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">{{ $course->modules_count }}</td>
                <td style="padding:12px 16px; color:#4A5252; font-size:0.875rem;">{{ $course->duration_hours }}h</td>
                <td style="padding:12px 16px;">
                    <span style="padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:600;
                        background:{{ $course->is_active ? '#E8F5F5' : '#F5F7F7' }};
                        color:{{ $course->is_active ? '#3A8C89' : '#8A9696' }};">
                        {{ $course->is_active ? 'Attivo' : 'Inattivo' }}
                    </span>
                </td>
                <td style="padding:12px 16px;">
                    <div style="display:flex; gap:8px;">
                        <a href="/admin/courses/{{ $course->id }}/edit" style="font-size:0.8rem; color:#55B1AE;">Modifica</a>
                        <a href="/admin/courses/{{ $course->id }}/modules" style="font-size:0.8rem; color:#8A9696;">Moduli</a>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection
