{{-- Rail a icone chiaro (64px) — punto di verità unico condiviso dai layout
     student / docente / scuola. Sostituisce la vecchia .sidebar scura 260px. --}}
<style>
    [x-cloak] { display: none !important; }
    body { font-family: 'Calibri', system-ui, sans-serif; }

    .rail {
        width: 64px; height: 100vh; background: #fff;
        border-right: 0.5px solid #E5E9E9;
        position: fixed; left: 0; top: 0; bottom: 0; z-index: 40;
        display: flex; flex-direction: column; align-items: center;
        padding: 16px 0; gap: 6px;
    }
    /* La parte centrale (nav) scrolla; il footer (avatar/contesto/logout) resta fisso. */
    .rail-scroll {
        flex: 1; min-height: 0; width: 100%;
        overflow-y: auto; overflow-x: hidden;
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        scrollbar-width: none;
    }
    .rail-scroll::-webkit-scrollbar { width: 0; height: 0; }

    .rail-mono {
        font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        font-weight: 800; letter-spacing: 0.04em; color: #1A1F1F; font-size: 1.05rem;
        width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
        text-decoration: none; margin-bottom: 4px; flex-shrink: 0;
    }

    .rail-item {
        position: relative; width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        color: #8A9696; text-decoration: none; transition: all 0.15s;
        flex-shrink: 0; background: none; border: none; cursor: pointer; padding: 0;
    }
    .rail-item:hover { background: rgba(85,177,174,0.15); color: #55B1AE; }
    .rail-item.active { background: rgba(85,177,174,0.15); color: #55B1AE; }
    .rail-item.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
    /* Corso insegnato: accento arancio sull'icona (badge "insegni" nel title). */
    .rail-item.teaching { color: #E28A53; }
    .rail-item.teaching:hover { background: rgba(226,138,83,0.14); color: #E28A53; }

    /* Badge realtime (messaggi/annunci): pallino arancio in alto a destra.
       Lo script Reverb scrive textContent (il numero) e toggla display: qui il
       testo è reso invisibile (font-size:0) così resta un pallino pulito. */
    .rail-badge-count {
        position: absolute; top: 4px; right: 4px;
        min-width: 9px; height: 9px; padding: 0; border-radius: 50%;
        background: #E28A53; border: 1.5px solid #fff;
        font-size: 0; line-height: 0; color: transparent; text-align: center;
    }

    .rail-sep { width: 24px; height: 1px; background: #E5E9E9; margin: 4px 0; flex-shrink: 0; }

    .rail-footer {
        flex-shrink: 0; width: 100%;
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        padding-top: 8px;
    }
    .rail-avatar {
        width: 34px; height: 34px; border-radius: 50%; background: #55B1AE;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 0.8rem; flex-shrink: 0;
        text-decoration: none;
    }
    .rail-avatar.demo { box-shadow: 0 0 0 2px #E28A53; }

    .main-content { margin-left: 64px; min-height: 100vh; background: #F5F7F7; }

    @media (max-width: 768px) {
        .rail { transform: translateX(-100%); transition: transform 0.3s; }
        .rail.open { transform: translateX(0); }
        .main-content { margin-left: 0; }
        .mobile-toggle { display: inline-flex !important; }
    }
</style>
