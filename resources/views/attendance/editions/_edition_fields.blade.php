{{-- Dati dell'edizione (creazione e modifica). --}}
@php $e = $edition ?? null; @endphp
<div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:12px;">
    <div><label class="ed-label">Nome edizione</label><input name="name" value="{{ old('name', $e?->name) }}" required maxlength="160" placeholder="es. Ottobre 2026 — Azienda X" class="ed-input" style="width:100%;"></div>
    <div><label class="ed-label">Modalità</label>
        <select name="modality" class="ed-input" style="width:100%;">
            @foreach(\App\Models\CourseEdition::MODALITIES as $v => $l)<option value="{{ $v }}" @selected(old('modality', $e?->modality ?? 'in_person') === $v)>{{ $l }}</option>@endforeach
        </select></div>
    <div><label class="ed-label">Formatore responsabile</label>
        <select name="instructor_id" class="ed-input" style="width:100%;">
            <option value="">—</option>
            @foreach($instructors as $i)<option value="{{ $i->id }}" @selected(old('instructor_id', $e?->instructor_id) === $i->id)>{{ $i->name }}</option>@endforeach
        </select></div>
</div>
<div style="display:grid; grid-template-columns:1fr 2fr; gap:12px; margin-top:12px;">
    <div><label class="ed-label">Sede</label><input name="location" value="{{ old('location', $e?->location) }}" maxlength="255" class="ed-input" style="width:100%;"></div>
    <div><label class="ed-label">Note</label><input name="notes" value="{{ old('notes', $e?->notes) }}" maxlength="2000" class="ed-input" style="width:100%;"></div>
</div>
