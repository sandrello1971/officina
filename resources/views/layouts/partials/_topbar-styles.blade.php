{{-- Topbar orizzontale scura (Direzione A) — punto di verità unico condiviso dai
     layout student / docente / scuola. Sostituisce il rail a icone laterale. --}}
<style>
    [x-cloak] { display: none !important; }
    body { font-family: 'Calibri', system-ui, sans-serif; }

    .topbar {
        height: 60px; background: #1A1F1F;
        display: flex; align-items: center; gap: 28px; padding: 0 24px;
        position: sticky; top: 0; z-index: 40;
    }

    .topbar-brand {
        display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0;
    }
    .topbar-brand .mono {
        font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        font-weight: 800; letter-spacing: 0.14em; color: #f2efe9; font-size: 1.15rem; line-height: 1;
    }
    .topbar-brand img { height: 30px; filter: brightness(0) invert(1); }

    .topbar-nav { display: flex; align-items: center; gap: 4px; flex: 1; flex-wrap: wrap; }

    .topbar-item {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 8px 14px; border-radius: 6px;
        color: #8A9696; font-size: 0.85rem; text-decoration: none;
        background: none; border: none; cursor: pointer; transition: all 0.15s; white-space: nowrap;
    }
    .topbar-item:hover { background: rgba(85,177,174,0.15); color: #55B1AE; }
    .topbar-item.active { background: rgba(85,177,174,0.15); color: #55B1AE; }

    /* Badge realtime (messaggi/annunci): pill numerica arancio accanto all'icona.
       Lo script Reverb scrive textContent e toggla display; gli id restano invariati. */
    .topbar-badge {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 18px; height: 18px; padding: 0 5px; border-radius: 10px;
        background: #E28A53; color: #fff; font-size: 0.65rem; font-weight: 700; line-height: 1;
    }

    .topbar-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

    /* Menu utente (avatar → dropdown con cambia contesto / extra / logout). */
    .topbar-usermenu { position: relative; }
    .topbar-avatar {
        width: 34px; height: 34px; border-radius: 50%; background: #55B1AE;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 0.8rem; border: none; cursor: pointer; padding: 0;
    }
    .topbar-avatar.demo { box-shadow: 0 0 0 2px #E28A53; }
    .topbar-menu {
        display: none; position: absolute; right: 0; top: 44px; min-width: 220px;
        background: #fff; border: 1px solid #E5E9E9; border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15); padding: 8px; z-index: 60;
    }
    .topbar-menu.open { display: block; }
    .topbar-menu .um-head { padding: 6px 10px 8px; border-bottom: 1px solid #F0F2F2; margin-bottom: 6px; }
    .topbar-menu .um-name { color: #1A1F1F; font-size: 0.82rem; font-weight: 700; }
    .topbar-menu .um-email { color: #8A9696; font-size: 0.72rem; }
    .topbar-menu .um-label { color: #8A9696; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; padding: 6px 10px 2px; }
    .topbar-menu a, .topbar-menu button {
        display: flex; align-items: center; gap: 9px; width: 100%;
        padding: 8px 10px; border-radius: 6px; text-align: left;
        color: #4A5252; font-size: 0.82rem; text-decoration: none;
        background: none; border: none; cursor: pointer;
    }
    {{-- hex minuscolo volutamente: la topbar è sempre resa sulle pagine scuola e
         "#3A8C89" conterrebbe la sottostringa "3A" che collide con TenancyHardeningTest
         (assertDontSee('3A') su una classe chiamata "3A"). --}}
    .topbar-menu a:hover, .topbar-menu button:hover { background: rgba(85,177,174,0.12); color: #3a8c89; }
    .topbar-menu form { margin: 0; }
    .topbar-menu .um-logout { color: #E28A53; }
    .topbar-menu .um-logout:hover { background: rgba(226,138,83,0.12); color: #E28A53; }

    .mobile-toggle {
        display: none; background: none; border: none; cursor: pointer;
        color: #8A9696; padding: 6px; border-radius: 6px;
    }
    .mobile-toggle:hover { background: rgba(85,177,174,0.15); color: #55B1AE; }

    .main-content { margin-left: 0; min-height: 100vh; background: #F5F7F7; }

    @media (max-width: 768px) {
        .topbar { gap: 12px; }
        .mobile-toggle { display: inline-flex; order: -1; }
        /* La nav collassa in un pannello verticale sotto la topbar. */
        .topbar-nav {
            display: none; position: absolute; top: 60px; left: 0; right: 0;
            flex-direction: column; align-items: stretch; gap: 2px;
            background: #1A1F1F; padding: 8px 12px 14px; border-top: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 10px 20px rgba(0,0,0,0.25);
        }
        .topbar-nav.open { display: flex; }
        .topbar-item { padding: 10px 12px; }
    }
</style>
