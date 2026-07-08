@extends('layouts.admin')
@section('title', 'Categorie corsi')
@section('content')

<div style="max-width:820px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Categorie corsi</h2>
        <a href="/admin/courses" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Corsi</a>
    </div>

    @if(session('success'))
    <div style="padding:10px 14px; background:rgba(85,177,174,0.12); border:1px solid rgba(85,177,174,0.4); border-radius:8px; color:#3A8C89; margin-bottom:14px; font-size:0.85rem;">✅ {{ session('success') }}</div>
    @endif
    @if ($errors->any())
    <div style="background:#FDECE2; border:1px solid #E28A53; color:#A8521F; border-radius:8px; padding:14px 16px; margin-bottom:16px; font-size:0.85rem;">
        <ul style="margin:0 0 0 18px; padding:0;">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif

    <p style="color:#8A9696; font-size:0.8rem; margin-bottom:16px;">La categoria è <strong>esclusiva</strong>: ogni corso ne ha al più una. I corsi senza categoria finiscono in «Altri corsi».</p>

    {{-- Nuova categoria --}}
    <div style="background:white; border-radius:10px; padding:18px; margin-bottom:20px;">
        <form method="POST" action="{{ route('admin.course-categories.store') }}" style="display:grid; grid-template-columns:2fr 1fr 90px auto; gap:12px; align-items:end;">
            @csrf
            <div>
                <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome *</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="Es. Sicurezza"
                       style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Colore</label>
                <input type="text" name="color" value="{{ old('color', '#55B1AE') }}"
                       style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            </div>
            <div>
                <label style="font-size:0.75rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ordine</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}"
                       style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            </div>
            <button type="submit" style="padding:9px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:700; cursor:pointer;">+ Aggiungi</button>
        </form>
    </div>

    {{-- Elenco categorie --}}
    <div style="background:white; border-radius:10px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#F5F7F7;">
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Categoria</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Colore</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Ordine</th>
                    <th style="padding:12px 16px; text-align:left; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Corsi</th>
                    <th style="padding:12px 16px; text-align:right; font-size:0.75rem; color:#8A9696; text-transform:uppercase; font-weight:700;">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                <tr style="border-bottom:1px solid #F5F7F7;">
                    <form method="POST" action="{{ route('admin.course-categories.update', $category->id) }}">
                        @csrf @method('PUT')
                        <td style="padding:10px 16px;">
                            <input type="text" name="name" value="{{ $category->name }}" required
                                   style="width:100%; padding:7px 10px; border:1px solid #E5E9E9; border-radius:6px; font-size:0.85rem; outline:none;">
                        </td>
                        <td style="padding:10px 16px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="width:16px; height:16px; border-radius:4px; background:{{ $category->color ?? '#55B1AE' }}; flex-shrink:0;"></span>
                                <input type="text" name="color" value="{{ $category->color }}"
                                       style="width:90px; padding:7px 10px; border:1px solid #E5E9E9; border-radius:6px; font-size:0.8rem; outline:none;">
                            </div>
                        </td>
                        <td style="padding:10px 16px;">
                            <input type="number" name="sort_order" value="{{ $category->sort_order }}"
                                   style="width:64px; padding:7px 10px; border:1px solid #E5E9E9; border-radius:6px; font-size:0.85rem; outline:none;">
                        </td>
                        <td style="padding:10px 16px; color:#4A5252; font-size:0.85rem;">{{ $category->courses_count }}</td>
                        <td style="padding:10px 16px; text-align:right; white-space:nowrap;">
                            <button type="submit" style="padding:6px 12px; background:#E8F5F5; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">Salva</button>
                    </form>
                            <form method="POST" action="{{ route('admin.course-categories.destroy', $category->id) }}" style="display:inline;"
                                  onsubmit="return confirm('Eliminare la categoria «{{ $category->name }}»? I corsi collegati resteranno senza categoria.');">
                                @csrf @method('DELETE')
                                <button type="submit" style="padding:6px 12px; background:white; color:#C52A2A; border:1px solid #E5B4B4; border-radius:6px; font-size:0.78rem; font-weight:600; cursor:pointer;">Elimina</button>
                            </form>
                        </td>
                </tr>
                @empty
                <tr><td colspan="5" style="padding:20px 16px; color:#8A9696; font-size:0.85rem; text-align:center;">Nessuna categoria. Creane una qui sopra.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
