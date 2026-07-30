{{-- Card lezione Schola (griglia "I miei corsi"). Parametri: $lesson, $class. --}}
<div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.06);">
    <div style="background:#55B1AE; padding:16px 20px; display:flex; align-items:center; justify-content:space-between;">
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="font-size:1.5rem;">&#128218;</span>
            <div>
                <div style="color:white; font-weight:700;">{{ $lesson->title }}</div>
                <div style="color:rgba(255,255,255,0.8); font-size:0.75rem;">
                    {{ $lesson->topic?->name ?? 'Senza argomento' }}
                </div>
            </div>
        </div>
        <span style="padding:4px 10px; background:rgba(255,255,255,0.22); color:white; border-radius:12px; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;">
            Lezione
        </span>
    </div>

    <div style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <div style="color:#8A9696; font-size:0.8rem;">
            Classe {{ $class->name }}@if($lesson->teacher?->name) &middot; {{ $lesson->teacher->name }}@endif
        </div>
        <a href="{{ route('student.classes.lesson.show', [$class, $lesson]) }}"
           style="padding:6px 16px; background:#55B1AE; color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none; white-space:nowrap;">
            Apri lezione &rarr;
        </a>
    </div>
</div>
