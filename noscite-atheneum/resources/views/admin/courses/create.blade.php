@extends('layouts.admin')
@section('title', 'Nuovo corso')
@section('content')

<div style="max-width:700px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
        <a href="/admin/courses" style="color:#8A9696; text-decoration:none; font-size:0.85rem;">&larr; Corsi</a>
        <span style="color:#C8D0D0;">|</span>
        <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F;">Nuovo corso</h2>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">
        <form method="POST" action="/admin/courses">
            @csrf

            <div style="display:grid; gap:16px;">
                <div style="display:grid; grid-template-columns:2fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Nome *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                        @error('name')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Slug</label>
                        <input type="text" name="slug" value="{{ old('slug') }}" placeholder="auto da nome"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                        @error('slug')<p style="color:#E28A53; font-size:0.75rem; margin-top:4px;">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione breve</label>
                    <input type="text" name="short_description" value="{{ old('short_description') }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Descrizione</label>
                    <textarea name="description" rows="4"
                              style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none; resize:vertical;">{{ old('description') }}</textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:16px;">
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Icona</label>
                        <input type="text" name="icon" value="{{ old('icon', '✦') }}" placeholder="🎯"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Colore</label>
                        <input type="text" name="color" value="{{ old('color', '#55B1AE') }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ore</label>
                        <input type="number" name="duration_hours" value="{{ old('duration_hours') }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                    <div>
                        <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Ordine</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}"
                               style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                    </div>
                </div>

                <div>
                    <label style="font-size:0.8rem; font-weight:600; color:#4A5252; display:block; margin-bottom:6px;">Certificazione</label>
                    <input type="text" name="certification_name" value="{{ old('certification_name') }}"
                           style="width:100%; padding:10px 14px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.875rem; outline:none;">
                </div>

                <div>
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span style="font-size:0.875rem; color:#1A1F1F;">Corso attivo</span>
                    </label>
                </div>

                <div style="display:flex; gap:12px; justify-content:flex-end; margin-top:8px;">
                    <a href="/admin/courses" style="padding:10px 20px; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.875rem; text-decoration:none;">Annulla</a>
                    <button type="submit" style="padding:10px 24px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer;">
                        Crea corso
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
