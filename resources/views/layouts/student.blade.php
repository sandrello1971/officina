<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Atheneum') — Atheneum Noscite</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com/3.4.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Calibri', system-ui, sans-serif; }
        .sidebar { width: 260px; min-height: 100vh; background: #1A1F1F; position: fixed; left: 0; top: 0; bottom: 0; overflow-y: auto; z-index: 40; }
        .main-content { margin-left: 260px; min-height: 100vh; background: #F5F7F7; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; color: #8A9696; font-size: 0.875rem; transition: all 0.2s; border-radius: 6px; margin: 2px 8px; text-decoration:none; }
        .nav-item:hover { background: rgba(85,177,174,0.1); color: #55B1AE; }
        .nav-item.active { background: rgba(85,177,174,0.15); color: #55B1AE; font-weight: 600; }
        .progress-bar { height: 6px; background: #C8D0D0; border-radius: 3px; overflow: hidden; }
        .progress-fill { height: 100%; background: #55B1AE; border-radius: 3px; transition: width 0.3s; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .mobile-toggle { display: inline-flex !important; }
        }
    </style>
    @livewireStyles
</head>
<body>

<aside class="sidebar">
    <div style="padding: 24px 20px; border-bottom: 1px solid rgba(85,177,174,0.2);">
        <img src="/images/logo.png" alt="Noscite" style="height:36px; filter:brightness(0) invert(1); margin-bottom:8px;">
        <div style="color:#55B1AE; font-size:0.75rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;">Atheneum</div>
        <div style="color:#8A9696; font-size:0.7rem; font-style:italic;">In digit&#x101;l&#x12B; nova virt&#x16B;s</div>
    </div>

    <div style="padding: 16px 20px; border-bottom: 1px solid rgba(85,177,174,0.1);">
        <div style="width:36px; height:36px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:0.875rem; margin-bottom:8px;">
            {{ strtoupper(substr(session('student_name', 'S'), 0, 1)) }}
        </div>
        <div style="color:#E8EDED; font-size:0.8rem; font-weight:600;">{{ session('student_name') }}</div>
        <div style="color:#8A9696; font-size:0.7rem;">{{ session('student_email') }}</div>
        @php $currentStudent = \App\Models\Student::find(session('student_id')); @endphp
        @if($currentStudent && $currentStudent->is_demo)
        <div style="margin-top:6px; padding:3px 8px; background:rgba(226,138,83,0.2); border:1px solid #E28A53; border-radius:4px; display:inline-block;">
            <span style="color:#E28A53; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Versione Demo</span>
        </div>
        @endif
    </div>

    <nav style="padding: 12px 0;">
        <a href="/learn/dashboard" class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
            <span>&#9632;</span> Dashboard
        </a>

        @php
            $sidebarStudent = \App\Models\Student::with(['courses' => fn($q) => $q->wherePivot('is_active', true)->orderBy('sort_order')])->find(session('student_id'));
            $firstCourse = $sidebarStudent?->courses->first();
        @endphp

        @if($sidebarStudent)
            @foreach($sidebarStudent->courses as $sidebarCourse)
            <a href="/learn/course/{{ $sidebarCourse->slug }}"
               class="nav-item {{ request()->is('learn/course/'.$sidebarCourse->slug.'*') ? 'active' : '' }}">
                <span>{{ $sidebarCourse->icon }}</span>
                <span>{{ $sidebarCourse->name }}</span>
            </a>
            @endforeach
        @endif

        <div style="margin: 16px 8px 4px; padding: 0 12px;">
            <div style="color:#4A5252; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em;">Supporto</div>
        </div>
        <a href="#" @click.prevent="$dispatch('minerva-toggle')" class="nav-item">
            <span>&#10022;</span> Assistente AI
        </a>
    </nav>

    <div style="position:absolute; bottom:0; left:0; right:0; padding:16px 20px; border-top:1px solid rgba(85,177,174,0.1);">
        <form method="POST" action="/learn/logout">
            @csrf
            <button type="submit" style="width:100%; padding:8px; background:rgba(226,138,83,0.1); color:#E28A53; border:1px solid rgba(226,138,83,0.3); border-radius:6px; font-size:0.8rem; cursor:pointer;">
                Esci
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <div style="background:white; padding:12px 24px; border-bottom:1px solid #C8D0D0; display:flex; align-items:center; gap:12px;">
        <button onclick="document.querySelector('.sidebar').classList.toggle('open')" class="mobile-toggle" style="display:none; background:none; border:none; cursor:pointer; color:#55B1AE; font-size:1.2rem;">&#9776;</button>
        <div style="font-size:0.875rem; color:#8A9696;">
            @yield('breadcrumb', 'Dashboard')
        </div>
    </div>

    @if(session('success'))
    <div style="margin:16px 24px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">
        &#10003; {{ session('success') }}
    </div>
    @endif

    <div style="padding:24px;">
        @yield('content')
    </div>
</div>

{{-- MINERVA BUBBLE --}}
<div x-data="minervaBubble()" x-init="init()"
     @minerva-toggle.window="toggle()"
     style="position:fixed; bottom:20px; right:20px; z-index:100;">

    <button x-show="!open" @click="toggle()"
            style="width:58px; height:58px; border-radius:50%; background:linear-gradient(135deg,#55B1AE,#3A8C89); color:white; border:none; cursor:pointer; box-shadow:0 4px 14px rgba(85,177,174,0.45); font-size:1.4rem; display:flex; align-items:center; justify-content:center;"
            title="Chiedi a Minerva">
        ✦
    </button>

    <div x-show="open" x-cloak x-transition
         style="width:380px; max-width:calc(100vw - 40px); height:560px; max-height:calc(100vh - 80px); background:white; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.2); display:flex; flex-direction:column; overflow:hidden;">

        <div style="background:linear-gradient(135deg,#1A1F1F,#3A8C89); padding:14px 18px; display:flex; align-items:center; gap:10px;">
            <div style="width:34px; height:34px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:1rem;">✦</div>
            <div style="flex:1;">
                <div style="color:white; font-weight:700; font-size:0.9rem;">Minerva</div>
                <div style="color:rgba(255,255,255,0.7); font-size:0.7rem;">Assistente AI — Atheneum</div>
            </div>
            <button @click="reset()" title="Nuova conversazione"
                    style="background:none; border:none; color:rgba(255,255,255,0.7); cursor:pointer; font-size:0.9rem;">↺</button>
            <button @click="toggle()" title="Chiudi"
                    style="background:none; border:none; color:rgba(255,255,255,0.85); cursor:pointer; font-size:1.2rem; line-height:1;">×</button>
        </div>

        <div x-ref="msgs" style="flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px; background:#F5F7F7;">
            <template x-if="messages.length === 0">
                <div style="padding:18px; background:white; border-radius:10px; color:#4A5252; font-size:0.85rem; line-height:1.6;">
                    Ciao! Sono <strong>Minerva</strong>. Fammi una domanda sui contenuti dei tuoi corsi.
                </div>
            </template>
            <template x-for="(msg, idx) in messages" :key="idx">
                <div>
                    <div x-show="msg.role === 'user'"
                         style="display:flex; justify-content:flex-end;">
                        <div style="max-width:85%; padding:10px 14px; background:#55B1AE; color:white; border-radius:12px 0 12px 12px; font-size:0.85rem; line-height:1.5;"
                             x-text="msg.content"></div>
                    </div>
                    <div x-show="msg.role === 'assistant'"
                         style="display:flex; gap:8px; align-items:flex-start;">
                        <div style="width:26px; height:26px; border-radius:50%; background:#55B1AE; display:flex; align-items:center; justify-content:center; color:white; font-size:0.7rem; flex-shrink:0;">✦</div>
                        <div style="max-width:85%; padding:10px 14px; background:white; color:#1A1F1F; border-radius:0 12px 12px 12px; font-size:0.85rem; line-height:1.6;">
                            <div class="minerva-md" x-html="renderMd(msg.content)"></div>
                            <button x-show="msg.mode === 'summary' && !msg.expanded"
                                    @click="expand(idx)"
                                    style="margin-top:10px; padding:5px 12px; background:#E8F5F5; color:#3A8C89; border:1px solid #55B1AE; border-radius:6px; font-size:0.75rem; font-weight:600; cursor:pointer;">
                                Vuoi maggiori info? →
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="typing" style="color:#8A9696; font-size:0.8rem; font-style:italic; padding:6px 10px;">Minerva sta pensando...</div>
        </div>

        <div style="background:white; border-top:1px solid #E8F5F5; padding:10px; display:flex; gap:8px;">
            <input type="text" x-model="draft" @keydown.enter="send()"
                   :disabled="typing"
                   placeholder="Scrivi una domanda..."
                   style="flex:1; padding:8px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.85rem; outline:none;">
            <button @click="send()" :disabled="typing || !draft.trim()"
                    :style="typing || !draft.trim() ? 'opacity:0.5;cursor:not-allowed;' : 'cursor:pointer;'"
                    style="padding:8px 16px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.85rem; font-weight:600;">
                Invia
            </button>
        </div>
    </div>
</div>

<style>
.minerva-md h1, .minerva-md h2, .minerva-md h3 { font-weight:700; color:#1A1F1F; margin:8px 0 4px; }
.minerva-md h2 { font-size:0.95rem; color:#3A8C89; }
.minerva-md h3 { font-size:0.88rem; }
.minerva-md p { margin:4px 0; }
.minerva-md ul, .minerva-md ol { margin:4px 0 4px 18px; }
.minerva-md li { margin:2px 0; }
.minerva-md strong { font-weight:700; color:#1A1F1F; }
.minerva-md em { font-style:italic; color:#4A5252; }
.minerva-md blockquote { margin:6px 0; padding:6px 10px; border-left:3px solid #55B1AE; background:#E8F5F5; color:#3A8C89; font-size:0.8rem; border-radius:0 6px 6px 0; }
.minerva-md code { background:#F5F7F7; padding:1px 5px; border-radius:3px; font-family:monospace; font-size:0.78rem; color:#E28A53; }
</style>

<script>
function minervaBubble() {
    return {
        open: false,
        draft: '',
        messages: [],
        typing: false,

        init() {
            try {
                const saved = localStorage.getItem('minerva-chat');
                if (saved) this.messages = JSON.parse(saved);
                this.open = localStorage.getItem('minerva-open') === '1';
            } catch(e) {}
        },

        toggle() {
            this.open = !this.open;
            localStorage.setItem('minerva-open', this.open ? '1' : '0');
            if (this.open) this.$nextTick(() => this.scrollBottom());
        },

        reset() {
            this.messages = [];
            localStorage.removeItem('minerva-chat');
        },

        persist() {
            try { localStorage.setItem('minerva-chat', JSON.stringify(this.messages)); } catch(e) {}
        },

        scrollBottom() {
            if (this.$refs.msgs) this.$refs.msgs.scrollTop = this.$refs.msgs.scrollHeight;
        },

        renderMd(text) {
            if (!text) return '';
            try {
                const html = window.marked ? window.marked.parse(text) : text;
                return window.DOMPurify ? window.DOMPurify.sanitize(html) : html;
            } catch(e) {
                return text.replace(/[<>]/g, c => c === '<' ? '&lt;' : '&gt;');
            }
        },

        buildHistory() {
            return this.messages
                .filter(m => m.role === 'user' || (m.role === 'assistant' && !m.placeholder))
                .map(m => ({ role: m.role, content: m.content }));
        },

        async send() {
            const q = this.draft.trim();
            if (!q || this.typing) return;
            this.draft = '';
            this.messages.push({ role: 'user', content: q });
            this.typing = true;
            this.persist();
            this.$nextTick(() => this.scrollBottom());

            try {
                const res = await fetch('/learn/minerva/ask', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        question: q,
                        history: this.buildHistory().slice(-10, -1),
                        mode: 'summary',
                    }),
                });
                const data = await res.json();
                this.messages.push({ role: 'assistant', content: data.answer, mode: 'summary', expanded: false });
            } catch(e) {
                this.messages.push({ role: 'assistant', content: 'Errore di connessione. Riprova.' });
            }

            this.typing = false;
            this.persist();
            this.$nextTick(() => this.scrollBottom());
        },

        async expand(idx) {
            const msg = this.messages[idx];
            if (!msg || msg.mode !== 'summary' || msg.expanded || this.typing) return;

            const userQ = [...this.messages].slice(0, idx).reverse().find(m => m.role === 'user');
            if (!userQ) return;

            this.typing = true;
            try {
                const res = await fetch('/learn/minerva/ask', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        question: userQ.content,
                        history: this.buildHistory().slice(0, idx).slice(-10),
                        mode: 'expand',
                    }),
                });
                const data = await res.json();
                this.messages.push({ role: 'assistant', content: data.answer, mode: 'expand', expanded: true });
                this.messages[idx].expanded = true;
            } catch(e) {
                this.messages.push({ role: 'assistant', content: 'Errore nell\'approfondimento. Riprova.' });
            }
            this.typing = false;
            this.persist();
            this.$nextTick(() => this.scrollBottom());
        },
    };
}
</script>

@livewireScripts
@stack('scripts')
</body>
</html>
