@extends('layouts.student')
@section('title', $module->title)
@section('breadcrumb', $course->name . ' > ' . $module->title)

@section('content')
<div id="reading-progress" style="position:fixed;top:0;left:240px;right:0;height:3px;background:#E8F5F5;z-index:50;">
    <div id="reading-bar" style="height:100%;background:#55B1AE;width:0%;transition:width 0.1s;"></div>
</div>

<div style="display:grid; grid-template-columns:1fr 280px; gap:24px; max-width:1100px;">

    <div>
        <div style="background:white; border-radius:12px; padding:24px; margin-bottom:20px;">
            <div style="color:#8A9696; font-size:0.75rem; margin-bottom:8px;">
                <a href="/learn/course/{{ $course->slug }}" style="color:#55B1AE;">{{ $course->name }}</a>
                &rsaquo; {{ $module->title }}
            </div>
            <h1 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:4px;">{{ $module->title }}</h1>
            @if($module->duration_minutes)
            <div style="color:#8A9696; font-size:0.8rem;">&#9201; {{ $module->duration_minutes }} minuti</div>
            @endif
        </div>

        @if($module->content)
        <div style="background:white; border-radius:12px; padding:28px; margin-bottom:20px;">
            <style>
                .module-content { font-family: 'Calibri', system-ui, sans-serif; line-height:1.8; color:#1A1F1F; }
                .module-content h2 { font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:2rem 0 1rem; padding-bottom:0.5rem; border-bottom:2px solid #E8F5F5; }
                .module-content h3 { font-size:1.1rem; font-weight:700; color:#3A8C89; margin:1.5rem 0 0.75rem; }
                .module-content h4 { font-size:1rem; font-weight:700; color:#4A5252; margin:1.2rem 0 0.5rem; }
                .module-content p { margin:0.75rem 0; font-size:0.95rem; color:#1A1F1F; }
                .module-content ul, .module-content ol { margin:0.75rem 0 0.75rem 1.5rem; }
                .module-content li { margin:0.4rem 0; font-size:0.95rem; color:#1A1F1F; line-height:1.7; }
                .module-content strong, .module-content b { color:#1A1F1F; font-weight:700; }
                .module-content em, .module-content i { color:#4A5252; font-style:italic; }
                .module-content blockquote {
                    margin:1.5rem 0; padding:1rem 1.5rem;
                    background:#E8F5F5; border-left:4px solid #55B1AE;
                    border-radius:0 8px 8px 0; color:#3A8C89; font-style:italic;
                }
                .module-content table { width:100%; border-collapse:collapse; margin:1.5rem 0; font-size:0.875rem; }
                .module-content th { background:#E8F5F5; color:#3A8C89; padding:10px 14px; text-align:left; font-weight:700; border:1px solid #C8D0D0; }
                .module-content td { padding:10px 14px; border:1px solid #C8D0D0; color:#1A1F1F; vertical-align:top; }
                .module-content tr:nth-child(even) td { background:#F5F7F7; }
                .module-content hr { border:none; border-top:1px solid #E8F5F5; margin:2rem 0; }
                .module-content a { color:#55B1AE; text-decoration:underline; }
                .module-content code { background:#F5F7F7; padding:2px 6px; border-radius:4px; font-family:monospace; font-size:0.875rem; color:#E28A53; }
                .module-content pre { background:#1A1F1F; color:#E8EDED; padding:16px; border-radius:8px; overflow-x:auto; margin:1rem 0; }
            </style>
            <div class="module-content">
                {!! $module->content !!}
            </div>
        </div>
        @else
        <div style="background:white; border-radius:12px; padding:28px; margin-bottom:20px; color:#8A9696; text-align:center;">
            <div style="font-size:2rem; margin-bottom:8px;">&#128196;</div>
            <p>Il contenuto di questo modulo sara disponibile a breve.</p>
            @if($module->description)
            <p style="margin-top:12px; font-size:0.85rem; line-height:1.6; color:#4A5252;">{{ $module->description }}</p>
            @endif
        </div>
        @endif

        @if($materials->isNotEmpty())
        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:20px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:12px; font-size:0.9rem;">📎 Materiali del modulo</h3>
            @foreach($materials as $material)
            <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid #F5F7F7;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:1.2rem;">
                        {{ $material->file_type === 'canvas' ? '🎯' : ($material->file_type === 'pdf' ? '📕' : ($material->file_type === 'video' ? '🎬' : '📄')) }}
                    </span>
                    <div>
                        <div style="font-size:0.85rem; font-weight:600; color:#1A1F1F;">{{ $material->title }}</div>
                        @if($material->description)
                        <div style="font-size:0.75rem; color:#8A9696;">{{ $material->description }}</div>
                        @endif
                    </div>
                </div>
                @if(isset($isDemo) && $isDemo && ($material->file_path || $material->external_url))
                    <span style="padding:5px 12px; background:#F5F7F7; color:#8A9696; border-radius:6px; font-size:0.75rem;">
                        🔒 Solo versione completa
                    </span>
                @elseif($material->file_type === 'canvas' && $material->file_path)
                    <a href="/storage/{{ $material->file_path }}" target="_blank"
                       style="padding:6px 14px; background:linear-gradient(135deg,#55B1AE,#3A8C89); color:white; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                        Apri canvas →
                    </a>
                @elseif($material->is_downloadable && $material->file_path)
                    <a href="/storage/{{ $material->file_path }}" download
                       style="padding:5px 12px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                        Scarica
                    </a>
                @elseif($material->external_url)
                    <a href="{{ $material->external_url }}" target="_blank"
                       style="padding:5px 12px; background:#E8F5F5; color:#3A8C89; border-radius:6px; font-size:0.75rem; font-weight:600; text-decoration:none;">
                        Apri →
                    </a>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if($quiz)
        <div style="background:linear-gradient(135deg,#E28A53,#c97a45); border-radius:12px; padding:20px; margin-bottom:20px;">
            <h3 style="color:white; font-weight:700; margin-bottom:4px; font-size:1rem;">📝 {{ $quiz->title }}</h3>
            <p style="color:rgba(255,255,255,0.85); font-size:0.8rem; margin-bottom:12px;">
                {{ $quiz->questions()->count() }} domande · Soglia: {{ $quiz->passing_score }}%
                @if($quiz->time_limit_minutes) · ⏱ {{ $quiz->time_limit_minutes }} min @endif
            </p>
            <a href="/learn/quiz/{{ $quiz->id }}"
               style="display:inline-block; padding:8px 20px; background:white; color:#c97a45; border-radius:6px; font-size:0.875rem; font-weight:700; text-decoration:none;">
                Inizia il quiz →
            </a>
        </div>
        @endif

        @if(isset($finalQuiz) && $finalQuiz)
        <div style="background:linear-gradient(135deg,#1A1F1F,#252B2B); border-radius:16px; padding:28px; margin-bottom:20px; border:2px solid rgba(85,177,174,0.3);">
            @if($certificationPassed)
            <div style="text-align:center;">
                <div style="font-size:3rem; margin-bottom:12px;">🏆</div>
                <h3 style="color:#55B1AE; font-weight:700; font-size:1.1rem; margin-bottom:8px;">
                    Esame finale superato!
                </h3>
                <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
                    Hai superato l'esame finale per {{ $course->name }}.
                </p>
                <div style="display:inline-block; padding:8px 20px; background:rgba(85,177,174,0.15); border:1px solid #55B1AE; border-radius:8px; color:#55B1AE; font-size:0.8rem; font-weight:700;">
                    {{ $course->certification_name }}
                </div>
                <div style="display:flex; gap:10px; justify-content:center; margin-top:16px;">
                    <a href="/learn/certificate/{{ $course->slug }}"
                       style="padding:10px 24px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:700; text-decoration:none;">
                        ⬇ Scarica certificato PDF
                    </a>
                    <a href="/learn/certificate/{{ $course->slug }}/view" target="_blank"
                       style="padding:10px 24px; border:1px solid #55B1AE; color:#55B1AE; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
                        👁 Anteprima
                    </a>
                </div>
            </div>
            @else
            <div style="display:flex; align-items:flex-start; gap:20px;">
                <div style="font-size:2.5rem; flex-shrink:0;">🎓</div>
                <div style="flex:1;">
                    <div style="color:#55B1AE; font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:6px;">
                        Esame finale
                    </div>
                    <h3 style="color:white; font-weight:700; font-size:1.1rem; margin-bottom:6px;">
                        {{ $finalQuiz->title }}
                    </h3>
                    <p style="color:#8A9696; font-size:0.8rem; margin-bottom:4px;">
                        {{ $finalQuiz->questions()->count() }} domande · Soglia: {{ $finalQuiz->passing_score }}%
                        @if($finalQuiz->time_limit_minutes) · ⏱ {{ $finalQuiz->time_limit_minutes }} minuti @endif
                    </p>
                    <p style="color:#8A9696; font-size:0.8rem; margin-bottom:16px;">
                        Al superamento ricevi: <span style="color:#55B1AE; font-weight:600;">{{ $course->certification_name }}</span>
                    </p>
                    <a href="/learn/quiz/{{ $finalQuiz->id }}"
                       style="display:inline-block; padding:12px 28px; background:#55B1AE; color:white; border-radius:8px; font-size:0.9rem; font-weight:700; text-decoration:none;">
                        Sostieni l'esame →
                    </a>
                </div>
            </div>
            @endif
        </div>
        @endif

        <div style="display:flex; justify-content:space-between; margin-top:8px; gap:12px;">
            @if($prevModule)
            <a href="/learn/course/{{ $course->slug }}/module/{{ $prevModule->id }}" style="padding:10px 20px; background:white; color:#55B1AE; border:1px solid #55B1AE; border-radius:8px; font-size:0.875rem; text-decoration:none;">
                &larr; {{ \Illuminate\Support\Str::limit($prevModule->title, 30) }}
            </a>
            @else
            <div></div>
            @endif

            @if($nextModule)
            <a href="/learn/course/{{ $course->slug }}/module/{{ $nextModule->id }}" style="padding:10px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.875rem; font-weight:600; text-decoration:none;">
                {{ \Illuminate\Support\Str::limit($nextModule->title, 30) }} &rarr;
            </a>
            @endif
        </div>
    </div>

    <div>
        <div style="background:white; border-radius:12px; padding:20px; margin-bottom:16px; position:sticky; top:80px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:12px; font-size:0.9rem;">Il tuo progresso</h3>

            @if($progress->status === 'completed')
            <div style="text-align:center; padding:12px; background:#E8F5F5; border-radius:8px; margin-bottom:12px;">
                <div style="font-size:1.5rem;">&#9989;</div>
                <div style="color:#3A8C89; font-weight:600; font-size:0.875rem;">Modulo completato!</div>
            </div>
            @else
            <button id="complete-btn" onclick="completeModule()" style="width:100%; padding:12px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.875rem; font-weight:700; cursor:pointer; margin-bottom:12px;">
                &#10003; Segna come completato
            </button>
            @endif

            <a href="/learn/chat/{{ $course->slug }}" style="display:block; text-align:center; padding:10px; background:#1A1F1F; color:#55B1AE; border-radius:8px; font-size:0.8rem; font-weight:600; text-decoration:none;">
                &#10022; Chiedi a Minerva
            </a>
        </div>

        <div style="background:white; border-radius:12px; padding:16px; margin-bottom:16px;">
            <h3 style="font-weight:700; color:#1A1F1F; margin-bottom:10px; font-size:0.85rem;">📝 Le tue note</h3>
            <textarea id="student-note"
                      placeholder="Scrivi i tuoi appunti su questo modulo..."
                      style="width:100%; min-height:120px; padding:10px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.8rem; outline:none; resize:vertical; color:#1A1F1F; line-height:1.6;">{{ $note?->content }}</textarea>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                <span id="note-status" style="font-size:0.75rem; color:#8A9696;"></span>
                <button onclick="saveNote()"
                        style="padding:5px 14px; background:#55B1AE; color:white; border:none; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                    Salva
                </button>
            </div>
        </div>

        <div style="background:white; border-radius:12px; padding:16px;">
            <div style="color:#8A9696; font-size:0.75rem; margin-bottom:8px; font-weight:700; text-transform:uppercase;">Corso</div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                <span>{{ $course->icon }}</span>
                <span style="font-weight:600; color:#1A1F1F; font-size:0.85rem;">{{ $course->name }}</span>
            </div>
            @if($module->duration_minutes)
            <div style="color:#8A9696; font-size:0.8rem;">&#9201; Durata: {{ $module->duration_minutes }} minuti</div>
            @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
async function completeModule() {
    const btn = document.getElementById('complete-btn');
    if (!btn) return;
    btn.disabled = true;
    btn.textContent = 'Salvataggio...';
    try {
        const response = await fetch('/learn/course/{{ $course->slug }}/module/{{ $module->id }}/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        if (response.ok || response.redirected) {
            btn.style.background = '#3A8C89';
            btn.textContent = 'Completato!';
            setTimeout(() => location.reload(), 600);
        } else {
            btn.disabled = false;
            btn.textContent = 'Segna come completato';
        }
    } catch(e) {
        btn.disabled = false;
        btn.textContent = 'Segna come completato';
    }
}

const noteTextarea = document.getElementById('student-note');
let noteTimer = null;

if (noteTextarea) {
    noteTextarea.addEventListener('input', () => {
        clearTimeout(noteTimer);
        document.getElementById('note-status').textContent = 'Modifica in corso...';
        noteTimer = setTimeout(saveNote, 2000);
    });
}

async function saveNote() {
    if (!noteTextarea) return;
    try {
        await fetch('/learn/notes/{{ $module->id }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ content: noteTextarea.value }),
        });
        document.getElementById('note-status').textContent = '✓ Salvato';
        setTimeout(() => {
            document.getElementById('note-status').textContent = '';
        }, 2000);
    } catch(e) {
        document.getElementById('note-status').textContent = 'Errore salvataggio';
    }
}

// Reading progress bar
const progressBar = document.getElementById('reading-bar');
if (progressBar) {
    window.addEventListener('scroll', () => {
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrolled = docHeight > 0 ? (window.scrollY / docHeight) * 100 : 0;
        progressBar.style.width = Math.min(scrolled, 100) + '%';
    });
}
</script>
@endpush

@endsection
