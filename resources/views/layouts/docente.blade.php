<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Area docente') — {{ $branding->instanceName() }}</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    @include('layouts.partials._topbar-styles')
    <style>
        /* Feedback UX: spinner per le operazioni async (vedi CLAUDE.md). */
        @keyframes nosc-spin { to { transform: rotate(360deg); } }
        .nosc-spin { display:inline-block; width:13px; height:13px; border:2px solid rgba(255,255,255,0.5); border-top-color:#fff; border-radius:50%; animation: nosc-spin 0.7s linear infinite; vertical-align:-2px; margin-right:6px; }
        button[disabled] { opacity:0.65; cursor:progress; }
    </style>
    @stack('styles')
</head>
<body>

@php
    // Messaggi non letti del docente (thread di tutte le sue classi).
    $docenteUnread = (int) \App\Models\ClassMessage::whereNull('read_at')
        ->where('sender_id', '!=', session('student_id'))
        ->whereHas('conversation', fn ($q) => $q->where('teacher_id', session('student_id')))
        ->count();
@endphp
<nav class="topbar">
    <button type="button" class="mobile-toggle" data-toggle="#docente-nav" title="Menu" aria-label="Menu">
        @include('layouts.partials._icon', ['name' => 'dashboard', 'size' => 22])
    </button>
    <a href="{{ route('docente.dashboard') }}" class="topbar-brand" title="{{ $branding->instanceName() }} — Area docente">
        <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->ownerLabel() }}">
    </a>

    <div class="topbar-nav" id="docente-nav">
        @include('layouts.partials._topbar-item', [
            'href' => route('docente.dashboard'), 'label' => 'Dashboard', 'icon' => 'dashboard',
            'active' => request()->routeIs('docente.dashboard'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('docente.classes.index'), 'label' => 'Classi', 'icon' => 'classes',
            'active' => request()->routeIs('docente.classes.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('docente.topics.index'), 'label' => 'Argomenti', 'icon' => 'topics',
            'active' => request()->routeIs('docente.topics.*') || request()->routeIs('docente.lessons.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('docente.materials.index'), 'label' => 'Materiali', 'icon' => 'materials',
            'active' => request()->routeIs('docente.materials.*') && !request()->routeIs('docente.materials.shared.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('docente.biblioteca.index'), 'label' => 'Biblioteca', 'icon' => 'library',
            'active' => request()->routeIs('docente.biblioteca.*') || request()->routeIs('docente.materials.shared.*'),
        ])
        @include('layouts.partials._topbar-item', [
            'href' => route('docente.messages.index'), 'label' => 'Messaggi', 'icon' => 'messages',
            'active' => request()->routeIs('docente.messages.*'),
            'badgeId' => 'docente-unread-badge', 'badgeCount' => $docenteUnread,
        ])
    </div>

    <div class="topbar-actions">
        <button type="button" x-data @click="$dispatch('minerva-toggle')" class="topbar-item" title="Assistente AI — {{ atheneum_setting('assistant_name', 'Minerva') }}">
            @include('layouts.partials._icon', ['name' => 'ai', 'size' => 20])
        </button>

        <div class="topbar-usermenu">
            <button type="button" class="topbar-avatar" data-toggle="#docente-usermenu" title="{{ session('student_name') }}">
                {{ strtoupper(substr(session('student_name', 'D'), 0, 1)) }}
            </button>
            <div id="docente-usermenu" class="topbar-menu">
                <div class="um-head">
                    <div class="um-name">{{ session('student_name') }}</div>
                    <div class="um-email">{{ session('student_email') }} · Docente</div>
                </div>
                @if(($identity['courses'] ?? false) || ($identity['secretary'] ?? false))
                <div class="um-label">Cambia contesto</div>
                @if($identity['courses'] ?? false)
                <a href="{{ route('student.dashboard') }}">@include('layouts.partials._icon', ['name' => 'course', 'size' => 18]) I miei corsi</a>
                @endif
                @if($identity['secretary'] ?? false)
                <a href="{{ route('scuola.dashboard') }}">@include('layouts.partials._icon', ['name' => 'secretary', 'size' => 18]) Segreteria</a>
                @endif
                @endif
                <div class="um-label">Account</div>
                <form method="POST" action="/learn/logout">
                    @csrf
                    <button type="submit" class="um-logout">@include('layouts.partials._icon', ['name' => 'logout', 'size' => 18]) Esci</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; gap:12px;">
        <div style="font-size:0.875rem; color:#8A9696;">@yield('breadcrumb', 'Area docente')</div>
    </div>

    @include('layouts.partials._flash')

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>
<script>
// Feedback UX globale (CLAUDE.md "Feedback UX — NON negoziabile"):
// ogni form con data-async, al submit, disabilita il bottone, mostra lo
// spinner con l'etichetta "in corso" e previene il doppio submit.
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-async')) return;
    if (form.dataset.submitting === '1') { e.preventDefault(); return; }
    form.dataset.submitting = '1';
    const btn = form.querySelector('button[type="submit"], button:not([type])');
    if (btn) {
        const label = btn.getAttribute('data-busy-label') || 'Attendere…';
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="nosc-spin"></span>' + label;
        // Disabilita dopo il tick così il form invia regolarmente i suoi dati.
        setTimeout(function () { btn.disabled = true; }, 0);
    }
}, true);
</script>
{{-- ===== Chatbot floating Minerva (docente): tutta la documentazione scolastica + fonti ===== --}}
<div x-data="docenteMinervaBubble()" x-init="init()" @minerva-toggle.window="toggle()"
     style="position:fixed; bottom:20px; right:20px; z-index:100;">

    <button x-show="!open" x-cloak @click="toggle()"
            style="width:58px; height:58px; border-radius:50%; background:linear-gradient(135deg,#55B1AE,#3A8C89); color:white; border:none; cursor:pointer; box-shadow:0 4px 14px rgba(85,177,174,0.45); font-size:1.4rem; display:flex; align-items:center; justify-content:center;"
            title="Chiedi a {{ atheneum_setting('assistant_name', 'Minerva') }}">✦</button>

    <div x-show="open" x-cloak x-transition class="minerva-panel">
        <div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); padding:14px 18px; display:flex; align-items:center; gap:10px;">
            <div style="width:34px; height:34px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:1rem;">✦</div>
            <div style="flex:1;">
                <div style="color:white; font-weight:700; font-size:0.9rem;">{{ atheneum_setting('assistant_name', 'Minerva') }}</div>
                <div style="color:rgba(255,255,255,0.7); font-size:0.7rem;">Assistente docente — documentazione di scuola</div>
            </div>
            <button @click="reset()" title="Nuova conversazione" style="background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; font-size:0.9rem;">↺</button>
            <button @click="toggle()" title="Chiudi" style="background:none; border:none; color:rgba(255,255,255,0.85); cursor:pointer; font-size:1.2rem; line-height:1;">×</button>
        </div>

        <div x-ref="msgs" class="minerva-msgs">
            <template x-if="messages.length === 0">
                <div style="padding:18px; background:white; border-radius:10px; color:#4A5252; font-size:0.85rem; line-height:1.6;">
                    Ciao! Sono <strong>{{ atheneum_setting('assistant_name', 'Minerva') }}</strong>. Chiedimi sui materiali della tua scuola (tuoi materiali, Biblioteca e classi che insegni). Ti indico anche le fonti.
                </div>
            </template>
            <template x-for="(msg, idx) in messages" :key="idx">
                <div>
                    <div x-show="msg.role === 'user'" style="display:flex; justify-content:flex-end;">
                        <div style="max-width:85%; padding:10px 14px; background:#55B1AE; color:white; border-radius:12px 0 12px 12px; font-size:0.85rem; line-height:1.5;" x-text="msg.content"></div>
                    </div>
                    <div x-show="msg.role === 'assistant'" style="display:flex; gap:8px; align-items:flex-start;">
                        <div style="width:26px; height:26px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:0.7rem; flex-shrink:0;">✦</div>
                        <div style="max-width:85%; padding:10px 14px; background:white; color:#1A1F1F; border-radius:0 12px 12px 12px; font-size:0.85rem; line-height:1.6;">
                            <div class="minerva-md" x-html="renderMd(msg.content)"></div>
                            <template x-if="msg.sources && msg.sources.length">
                                <div class="minerva-sources">Fonti:
                                    <template x-for="(s, j) in msg.sources" :key="j">
                                        <span>
                                            <template x-if="s.url"><a :href="s.url" target="_blank" rel="noopener" x-text="s.title + (s.timestamp ? ' [' + s.timestamp + ']' : '')"></a></template>
                                            <template x-if="!s.url"><span class="src" x-text="s.title + (s.timestamp ? ' [' + s.timestamp + ']' : '')"></span></template>
                                        </span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="typing" style="color:#8A9696; font-size:0.8rem; font-style:italic; padding:6px 10px;">{{ atheneum_setting('assistant_name', 'Minerva') }} sta pensando...</div>
        </div>

        <div style="background:white; border-top:1px solid #E8F5F5; padding:10px; display:flex; gap:8px;">
            <input type="text" x-model="draft" @keydown.enter="send()" :disabled="typing" placeholder="Scrivi una domanda..."
                   style="flex:1; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            <button @click="send()" :disabled="typing || !draft.trim()" :style="typing || !draft.trim() ? 'opacity:0.5;cursor:not-allowed;' : 'cursor:pointer;'"
                    style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600;">Invia</button>
        </div>
    </div>
</div>

<style>
.minerva-md h1, .minerva-md h2, .minerva-md h3 { font-weight:700; color:#1A1F1F; margin:8px 0 4px; }
.minerva-md h2 { font-size:0.95rem; color:#3A8C89; } .minerva-md h3 { font-size:0.88rem; }
.minerva-md p { margin:4px 0; } .minerva-md ul, .minerva-md ol { margin:4px 0 4px 18px; } .minerva-md li { margin:2px 0; }
.minerva-md strong { font-weight:700; color:#1A1F1F; } .minerva-md em { font-style:italic; color:#4A5252; }
.minerva-md code { background:#F5F7F7; padding:1px 5px; border-radius:3px; font-family:monospace; font-size:0.78rem; color:#E28A53; }
.minerva-panel { width:380px; max-width:calc(100vw - 40px); height:560px; max-height:calc(100vh - 80px); background:white; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.2); display:flex; flex-direction:column; overflow:hidden; }
.minerva-msgs { flex:1 1 0 !important; min-height:0 !important; overflow-y:auto !important; padding:14px; display:flex; flex-direction:column; gap:10px; background:#F5F7F7; }
.minerva-sources { margin-top:8px; font-size:0.72rem; color:#8A9696; display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
.minerva-sources a, .minerva-sources span.src { display:inline-block; background:#E8F5F5; color:#3A8C89; border:1px solid #C8E4E3; border-radius:10px; padding:2px 9px; text-decoration:none; }
.minerva-sources a:hover { background:#D6EEEE; }
[x-cloak] { display:none !important; }
</style>

@pushOnce('scripts','marked')<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>@endPushOnce
@pushOnce('scripts','dompurify')<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>@endPushOnce
@pushOnce('scripts','alpine-cdn')<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>@endPushOnce
@push('scripts')
<script>
function docenteMinervaBubble() {
    return {
        open: false, draft: '', messages: [], typing: false,
        init() {
            try {
                const cur = @json(session('student_id') ?? '');
                if (localStorage.getItem('minerva-doc-user') !== cur) {
                    localStorage.removeItem('minerva-doc-chat'); localStorage.removeItem('minerva-doc-open');
                    localStorage.setItem('minerva-doc-user', cur); this.messages = []; this.open = false; return;
                }
                const saved = localStorage.getItem('minerva-doc-chat');
                if (saved) this.messages = JSON.parse(saved);
                this.open = localStorage.getItem('minerva-doc-open') === '1';
            } catch(e) {}
        },
        toggle() { this.open = !this.open; localStorage.setItem('minerva-doc-open', this.open ? '1':'0'); if (this.open) this.$nextTick(() => this.scrollBottom()); },
        reset() { this.messages = []; localStorage.removeItem('minerva-doc-chat'); },
        persist() { try { localStorage.setItem('minerva-doc-chat', JSON.stringify(this.messages)); } catch(e) {} },
        scrollBottom() { if (this.$refs.msgs) this.$refs.msgs.scrollTop = this.$refs.msgs.scrollHeight; },
        renderMd(text) {
            if (!text) return '';
            try { const html = window.marked ? window.marked.parse(text) : text; return window.DOMPurify ? window.DOMPurify.sanitize(html) : html; }
            catch(e) { return text.replace(/[<>]/g, c => c === '<' ? '&lt;' : '&gt;'); }
        },
        buildHistory() { return this.messages.filter(m => m.role === 'user' || m.role === 'assistant').map(m => ({role: m.role, content: m.content})); },
        async send() {
            const q = this.draft.trim();
            if (!q || this.typing) return;
            this.draft = ''; this.messages.push({role:'user', content:q}); this.typing = true; this.persist();
            this.$nextTick(() => this.scrollBottom());
            try {
                const res = await fetch('{{ route('docente.minerva.ask') }}', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({question: q, history: this.buildHistory().slice(-10, -1)}),
                });
                const data = await res.json();
                this.messages.push({role:'assistant', content: data.answer || 'Risposta non disponibile.', sources: data.sources || []});
            } catch(e) {
                this.messages.push({role:'assistant', content:'Errore di connessione. Riprova.', sources: []});
            }
            this.typing = false; this.persist(); this.$nextTick(() => this.scrollBottom());
        },
    };
}
</script>
@endpush
@stack('scripts')
@include('layouts.partials._topbar-scripts')
</body>
</html>
