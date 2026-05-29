<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="Mappa di Maturità AI Noscite: valuta in 3 minuti la maturità AI Act della tua PMI su 5 dimensioni chiave. Ricevi report PDF personalizzato.">
<title>Mappa di Maturità AI — Assessment AI Act gratuito · Noscite</title>
<link rel="icon" type="image/png" href="/images/logo.png">
<style>
/* === Design system Noscite (Marketing-04) === */
:root{
  --teal:#55B1AE;--teal-dark:#3D8B88;--teal-light:#E8F5F5;
  --orange:#E28A53;--orange-dark:#d4784a;
  --gray-bg:#F5F7F7;--gray-border:#D1D5DB;
  --text:#1F2937;--text-light:#6B7280;--white:#FFFFFF;
  --error:#DC2626;--error-light:rgba(220,38,38,.10);
  --l1:#e05555;--l2:#e09f35;--l3:#55B1AE;--l4:#3D8B88;

  /* Compat: variabili usate dal markup esistente, rimappate al tema chiaro */
  --dark:var(--gray-bg);--panel:var(--white);--card:var(--white);--dark2:var(--gray-bg);
  --n2:var(--text-light);--n3:var(--text);--n4:var(--text);--muted:var(--text-light);
  --border:var(--gray-border);--border-s:var(--teal);
  --teal-dim:var(--teal-light);
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--gray-bg);color:var(--text);font-family:Calibri,"Segoe UI","Trebuchet MS",sans-serif;font-size:15px;min-height:100vh;line-height:1.5}
.wrap{max-width:920px;margin:0 auto;padding:20px;position:relative;z-index:1}

/* HEADER (design system) */
header{background:var(--white);border-left:6px solid var(--teal);padding:22px 26px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.05);margin-bottom:18px}
.logo-row{display:flex;align-items:center;margin-bottom:12px}
.logo-img{height:40px;width:auto}
.tag{display:inline-block;font-size:11pt;letter-spacing:2px;text-transform:uppercase;color:var(--teal);font-weight:600;margin-bottom:6px}
h1{font-size:24pt;font-weight:700;color:var(--text);line-height:1.2;margin-bottom:4px}
h1 em{color:var(--teal);font-style:normal}
.header-sub{font-size:11pt;color:var(--text-light);line-height:1.5;max-width:640px;margin-top:2px}
.motto{color:var(--orange);font-style:italic;font-size:10pt;margin-top:10px}

/* INTRO-BOX (riutilizzabile) */
.intro-box{background:var(--teal-light);padding:18px 22px;border-radius:8px;font-size:11pt;color:var(--text);margin-bottom:18px}
.intro-box strong{color:var(--teal-dark)}

/* CARD sezioni (.sec) */
.sec{background:var(--white);border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.05);padding:22px 26px;margin-bottom:18px;position:relative;overflow:hidden}
.sec::before{content:"";position:absolute;top:0;left:0;bottom:0;width:3px;background:var(--teal)}
.sec-title{font-size:11pt;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--teal-dark);margin-bottom:4px}
.sec-desc{font-size:11pt;color:var(--text-light);margin-bottom:18px;line-height:1.5}

/* FORM */
.info-row{display:grid;grid-template-columns:1fr 1fr;gap:13px}
.info-row-3{display:grid;grid-template-columns:1fr 1fr;gap:13px;margin-top:13px}
.fg{display:flex;flex-direction:column;gap:5px}
.fg label{font-size:10pt;color:var(--text);font-weight:600}
.fg label .req{color:var(--orange);margin-left:2px}
input[type=text],input[type=email],input[type=tel]{background:var(--white);border:1px solid var(--gray-border);border-radius:4px;color:var(--text);font-family:Calibri,"Segoe UI",sans-serif;font-size:11pt;padding:8px 10px;width:100%;outline:none;transition:border-color .18s,box-shadow .18s}
input[type=text]:focus,input[type=email]:focus,input[type=tel]:focus{border-color:var(--teal);outline:2px solid var(--teal);outline-offset:-1px}
input.has-error{border-color:var(--error);box-shadow:0 0 0 2px var(--error-light)}
input::placeholder{color:var(--text-light);opacity:.6}
.field-error{color:var(--error);font-size:10pt;margin-top:3px;display:none}
.field-error.show{display:block}

.consent-row{margin-top:18px;display:flex;align-items:flex-start;gap:10px;padding:14px;background:var(--gray-bg);border:1px solid var(--gray-border);border-radius:6px}
.consent-row input[type=checkbox]{margin-top:3px;flex-shrink:0;width:16px;height:16px;accent-color:var(--teal);cursor:pointer}
.consent-row label{font-size:10.5pt;color:var(--text);line-height:1.5;cursor:pointer}
.consent-row label a{color:var(--teal-dark);text-decoration:underline}

.hp-field{position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none}

/* BOTTONI (design system) */
.cta-start{margin-top:22px;text-align:center}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:6px;font-family:Calibri,"Segoe UI",sans-serif;font-size:11pt;font-weight:600;cursor:pointer;border:none;transition:all .18s;text-decoration:none}
.btn-primary{background:var(--teal);color:var(--white)}
.btn-primary:hover:not(:disabled){background:var(--teal-dark);transform:translateY(-1px)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed}
.btn-cta{background:var(--orange);color:var(--white)}
.btn-cta:hover:not(:disabled){background:var(--orange-dark);transform:translateY(-1px)}
.btn-cta:disabled{opacity:.5;cursor:not-allowed}
.btn-outline,.btn-secondary{background:var(--white);color:var(--text);border:1px solid var(--gray-border)}
.btn-outline:hover,.btn-secondary:hover{background:var(--gray-bg);border-color:var(--teal)}
.btn svg{width:15px;height:15px}
.btn .spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* GRID + DIMENSIONI */
.main-grid{display:grid;grid-template-columns:1fr 310px;gap:22px;align-items:start}

.dim-card{position:relative;background:var(--gray-bg);border:1px solid var(--gray-border);border-left:3px solid var(--gray-border);border-radius:6px;padding:14px 16px;margin-bottom:13px;transition:border-left-color .2s}
.dim-card:hover{border-left-color:var(--teal)}
.dim-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
.dim-num{width:28px;height:28px;flex-shrink:0;background:var(--teal-light);border:1px solid var(--teal);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11pt;font-weight:700;color:var(--teal-dark)}
.dim-name{font-size:11pt;font-weight:700;color:var(--text);margin-bottom:2px}
.dim-desc{font-size:10pt;color:var(--text-light);line-height:1.4}
.level-display{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:10px}
.level-score{font-size:28pt;font-weight:700;color:var(--text-light);line-height:1;transition:color .25s}
.level-text{font-size:10pt;color:var(--text-light);text-align:right;max-width:195px;line-height:1.4;transition:color .25s}

.slider-wrap{padding-bottom:18px}
input[type=range]{-webkit-appearance:none;width:100%;height:5px;
  background:linear-gradient(90deg,#e05555 0%,#e09f35 33%,#55B1AE 66%,#3D8B88 100%);
  border-radius:3px;outline:none;cursor:pointer}
input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:20px;height:20px;border-radius:50%;
  background:var(--teal);border:3px solid var(--white);
  box-shadow:0 0 0 2px var(--teal),0 1px 3px rgba(0,0,0,.15);margin-top:-7.5px;transition:box-shadow .18s}
input[type=range]:hover::-webkit-slider-thumb{box-shadow:0 0 0 3px var(--teal),0 1px 4px rgba(0,0,0,.2)}
.ticks{display:flex;justify-content:space-between;margin-top:4px}
.ticks span{font-size:9pt;color:var(--text-light)}
.note-area{background:var(--white);border:1px solid var(--gray-border);border-radius:4px;padding:8px 10px;margin-top:9px}
.note-area label{font-size:9pt;color:var(--text-light);font-weight:600;display:block;margin-bottom:4px}
textarea{width:100%;background:transparent;border:none;outline:none;font-family:Calibri,"Segoe UI",sans-serif;font-size:11pt;color:var(--text);resize:vertical;min-height:48px;line-height:1.5}
textarea::placeholder{color:var(--text-light);opacity:.55}

/* RADAR */
.radar-sticky{position:sticky;top:20px}
.radar-card{background:var(--white);border:1px solid var(--gray-border);border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.05);padding:20px;text-align:center}
.radar-title{font-size:12pt;font-weight:700;color:var(--text);margin-bottom:2px}
.radar-sub{font-size:9pt;color:var(--text-light);margin-bottom:13px}
canvas#radar{display:block;margin:0 auto;width:100%;max-width:260px}
.score-block{margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-border)}
.score-big{font-size:40pt;font-weight:700;color:var(--teal);line-height:1}
.score-of{font-size:9pt;color:var(--text-light)}
.profile-badge{display:inline-block;margin-top:9px;padding:5px 13px;border-radius:20px;font-size:10pt;font-weight:600;border:1px solid var(--gray-border);color:var(--text-light);background:var(--white);transition:all .3s}
.reco-block{background:var(--teal-light);border:1px solid var(--teal);border-radius:8px;padding:16px;margin-top:13px;text-align:left;display:none}
.reco-label{font-size:9pt;color:var(--teal-dark);font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:5px}
.reco-course{font-size:13pt;font-weight:700;color:var(--text);margin-bottom:4px}
.reco-desc{font-size:10pt;color:var(--text-light);line-height:1.5}

/* SUMMARY + GAP */
#assessment-sec,#summary-sec,#submit-sec{display:none}
.sum-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:9px;margin-bottom:14px}
.sum-item{background:var(--gray-bg);border:1px solid var(--gray-border);border-radius:6px;padding:11px 7px;text-align:center}
.sum-score{font-size:22pt;font-weight:700}
.sum-name{font-size:9pt;color:var(--text-light);text-transform:uppercase;letter-spacing:.5px;margin-top:3px;line-height:1.3}
.gap-row{display:flex;align-items:center;gap:11px;margin-bottom:10px}
.gap-name{font-size:10pt;color:var(--text);width:148px;flex-shrink:0}
.gap-track{flex:1;height:8px;background:rgba(0,0,0,.06);border-radius:4px;overflow:hidden}
.gap-fill{height:100%;border-radius:4px;transition:width .75s cubic-bezier(.4,0,.2,1)}
.gap-lbl{font-size:10pt;font-weight:700;width:46px;text-align:right}

/* SUBMIT CTA finale */
.submit-sec{background:var(--teal-light);border:1px solid var(--teal);border-radius:8px;padding:28px;text-align:center;margin-bottom:24px}
.submit-title{font-size:16pt;font-weight:700;color:var(--teal-dark);margin-bottom:6px}
.submit-sub{font-size:11pt;color:var(--text);margin-bottom:18px;line-height:1.5;max-width:520px;margin-left:auto;margin-right:auto}

/* ALERTS */
.alert{padding:12px 16px;border-radius:6px;margin:14px 0;font-size:11pt;line-height:1.5;text-align:left;display:none}
.alert.show{display:block}
.alert-error{background:var(--error-light);border:1px solid var(--error);color:var(--error)}
.alert-info{background:var(--teal-light);border:1px solid var(--teal);color:var(--teal-dark)}

/* FOOTER */
footer{text-align:center;color:var(--text-light);font-size:10pt;padding:24px 16px 16px;margin-top:24px}
footer .motto{color:var(--orange);margin:4px 0}
footer a{color:var(--text-light)}

@media(max-width:700px){
  .main-grid{grid-template-columns:1fr}
  .radar-sticky{position:static}
  .sum-grid{grid-template-columns:repeat(3,1fr)}
  .info-row,.info-row-3{grid-template-columns:1fr}
  .wrap{padding:14px}
  header{padding:18px 20px}
  h1{font-size:20pt}
}

/* === Info tooltip/popover (Marketing-03) — preserved, ri-stilizzato per fondo chiaro === */
.info-trigger{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;margin-left:8px;border:none;border-radius:50%;background:var(--teal);color:#fff;font-size:12px;font-weight:bold;font-family:Georgia,serif;font-style:italic;cursor:pointer;vertical-align:middle;line-height:1;padding:0;transition:background .15s ease}
.info-trigger:hover,.info-trigger:focus-visible{background:var(--teal-dark);outline:2px solid var(--teal-dark);outline-offset:2px}
.info-popover{position:absolute;z-index:1000;top:56px;left:56px;right:18px;max-width:380px;background:var(--white);border:1px solid var(--teal-light);border-left:4px solid var(--teal);border-radius:6px;box-shadow:0 6px 24px rgba(0,0,0,.18);padding:14px 16px;font-size:11pt;line-height:1.5;color:var(--text);text-align:left;display:none}
.info-popover.open{display:block}
.info-popover .info-alert{color:var(--orange);font-weight:600}
.info-popover .info-norm{font-weight:600;color:var(--teal-dark)}
.info-popover strong{color:var(--text)}
@media(max-width:600px){.info-popover{left:14px;right:14px;top:56px;max-width:none}}
</style>
</head>
<body>
<div class="wrap">
<header>
  <img src="/images/logo.png" alt="Noscite" class="logo-img">
  <div class="tag">Canvas Noscite · Maturità AI</div>
  <h1>Mappa di Maturità <em>AI</em></h1>
  <p class="header-sub">Valuta la tua azienda su 5 dimensioni chiave dell'adozione AI in conformità EU AI Act. Compila in 3 minuti e ricevi via email il report PDF personalizzato con la raccomandazione formativa Noscite più adatta alla tua PMI.</p>
  <div class="motto">In digitālī nova virtūs</div>
</header>

<form id="lead-form" autocomplete="on" novalidate>
@csrf

{{-- Honeypot anti-bot: deve restare vuoto --}}
<div class="hp-field" aria-hidden="true">
  <label for="website">Sito web (non compilare)</label>
  <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="">
</div>

<div class="sec" id="lead-sec">
  <div class="sec-title">I tuoi dati</div>
  <div class="sec-desc">Per inviarti il report personalizzato e contattarti per approfondire il percorso più adatto alla tua azienda.</div>

  <div class="info-row">
    <div class="fg"><label>Nome <span class="req">*</span></label><input type="text" name="first_name" id="first_name" maxlength="100" required placeholder="Mario"><span class="field-error" data-for="first_name"></span></div>
    <div class="fg"><label>Cognome <span class="req">*</span></label><input type="text" name="last_name" id="last_name" maxlength="100" required placeholder="Rossi"><span class="field-error" data-for="last_name"></span></div>
  </div>

  <div class="info-row-3">
    <div class="fg"><label>Azienda <span class="req">*</span></label><input type="text" name="company_name" id="company_name" maxlength="255" required placeholder="Rossi Metalli Srl"><span class="field-error" data-for="company_name"></span></div>
    <div class="fg"><label>Ruolo</label><input type="text" name="role" id="role" maxlength="150" placeholder="Direttore Operations"><span class="field-error" data-for="role"></span></div>
  </div>

  <div class="info-row-3">
    <div class="fg"><label>Email aziendale <span class="req">*</span></label><input type="email" name="email" id="email" maxlength="255" required placeholder="mario.rossi@example.it"><span class="field-error" data-for="email"></span></div>
    <div class="fg"><label>Telefono</label><input type="tel" name="phone" id="phone" maxlength="50" placeholder="+39 333 1234567"><span class="field-error" data-for="phone"></span></div>
  </div>

  <div class="consent-row">
    <input type="checkbox" id="gdpr_consent" name="gdpr_consent" value="1" required>
    <label for="gdpr_consent">
      Ho letto e accetto l'<a href="/privacy-policy" target="_blank" rel="noopener">informativa sulla privacy</a> e acconsento al trattamento dei miei dati per ricevere il report personalizzato e contatti commerciali da Noscite Srls. <span class="req">*</span>
    </label>
  </div>
  <span class="field-error" data-for="gdpr_consent"></span>

  <div class="cta-start">
    <button type="button" class="btn btn-primary" id="btn-start">
      Inizia l'assessment
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
  </div>
</div>

<div class="sec" id="assessment-sec">
  <div class="sec-title">Le 5 Dimensioni di Maturità AI</div>
  <div class="sec-desc">Per ogni dimensione leggi le descrizioni dei 4 livelli e posiziona il cursore sulla tua realtà aziendale <em>oggi</em> — non quella che vorresti. La valutazione onesta è l'unica che produce raccomandazioni utili.</div>
  <div class="main-grid">
    <div id="dims-col"></div>
    <div class="radar-sticky">
      <div class="radar-card">
        <div class="radar-title">Il tuo profilo AI</div>
        <div class="radar-sub">Si aggiorna in tempo reale</div>
        <canvas id="radar" width="260" height="260"></canvas>
        <div class="score-block">
          <div class="score-big" id="tot-score">—</div>
          <div class="score-of">/ 20 punti</div>
          <div class="profile-badge" id="prof-badge">Compila l'assessment →</div>
        </div>
        <div class="reco-block" id="reco-block">
          <div class="reco-label">Percorso Noscite consigliato</div>
          <div class="reco-course" id="reco-course"></div>
          <div class="reco-desc" id="reco-desc"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="sec" id="summary-sec">
  <div class="sec-title">Riepilogo — La tua fotografia oggi</div>
  <div class="sec-desc">Le dimensioni con punteggio più basso sono le priorità formative più urgenti.</div>
  <div class="sum-grid" id="sum-grid"></div>
  <div id="gap-bars"></div>
</div>

<div class="submit-sec" id="submit-sec">
  <div class="submit-title">Ricevi il tuo report personalizzato</div>
  <div class="submit-sub">Compila tutte e 5 le dimensioni e ricevi via email il PDF con la tua Mappa di Maturità AI e la raccomandazione formativa Noscite più adatta. Un consulente ti contatterà entro 24 ore.</div>

  <div class="alert alert-error" id="submit-error" role="alert"></div>

  <button type="submit" class="btn btn-cta" id="btn-submit" disabled>
    <span class="btn-label">Ricevi il report PDF</span>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
  </button>
  <div style="font-size:11px;color:var(--muted);margin-top:10px">Nessuna carta di credito · Cancellabile in qualsiasi momento</div>
</div>

</form>

<footer>
  <div>Canvas Noscite · Maturità AI · v1.0</div>
  <div class="motto">In digitālī nova virtūs</div>
  <div style="margin-top:6px;font-size:9pt;">Noscite Srls · Corsico (MI) · P.IVA 14385240966 · <a href="/privacy-policy">Privacy</a> · noscite.it</div>
</footer>
</div>

<script>
const DIMS=[
  {key:"tools",name:"Utilizzo degli Strumenti AI",desc:"Con quale frequenza e sistematicità vengono usati strumenti AI in azienda?",levels:["Non usiamo nessuno strumento AI in azienda.","Qualcuno ha provato ChatGPT o simili, ma in modo saltuario e personale.","Usiamo strumenti AI regolarmente ma senza workflow definiti o standard condivisi.","Usiamo l'AI con workflow definiti, team formato, risultati documentati e misurabili."]},
  {key:"governance",name:"Governance dei Dati",desc:"Esistono regole chiare su quali dati possono essere condivisi con strumenti AI?",levels:["Nessuna regola — ognuno usa gli strumenti AI come crede, senza vincoli.","Regole informali non scritte, condivise solo verbalmente in modo occasionale.","Regole scritte parziali, non complete o non diffuse uniformemente al team.","Policy documentata, condivisa con tutto il team, aggiornata regolarmente."]},
  {key:"skills",name:"Competenze del Team",desc:"Il personale sa usare l'AI in modo produttivo, sicuro e verificato?",levels:["Nessuna formazione ricevuta — usiamo l'AI per tentativi ed errori.","Qualche autodidatta sparso — nessuna formazione strutturata o condivisa.","Formazione parziale per alcuni ruoli — non sistematica né verificata.","Formazione strutturata per ruoli specifici, con verifica e aggiornamento periodico."]},
  {key:"processes",name:"Processi Digitalizzati",desc:"I processi aziendali sono documentati abbastanza da poter essere assistiti dall'AI?",levels:["Processi taciti — esistono solo nella testa delle persone, non scritti.","Documentazione parziale e datata, non accessibile a tutti in modo sistematico.","Processi scritti ma non sempre aggiornati o effettivamente seguiti.","Processi strutturati, aggiornati, accessibili a tutti e realmente applicati."]},
  {key:"compliance",name:"Conformità Normativa (AI Act)",desc:"L'azienda conosce e rispetta gli obblighi dell'EU AI Act Art. 4?",levels:["Non conosco l'EU AI Act né gli obblighi di formazione che impone.","Ne ho sentito parlare vagamente ma non so cosa comporti per la nostra azienda.","So che ci riguarda come organizzazione ma non abbiamo ancora preso misure concrete.","Abbiamo avviato azioni concrete: formazione documentata, policy interna, referente AI nominato."]}
];
const LC=["#e05555","#e09f35","#55B1AE","#3A8C89"];
const LB=["Assente","Iniziale","In sviluppo","Strutturato"];
const SH=["Utilizzo","Governance","Competenze","Processi","Compliance"];
const scores=[0,0,0,0,0];

// ---------- DIM rendering ----------
// Info popover content per dimensione (Marketing-03)
const INFOS={
  tools:`Misura quanto l'AI è usata in modo sistematico e governato dall'azienda. <span class="info-alert">Nota:</span> gli strumenti con licenze personali usati per lavoro (es. account ChatGPT individuali) costituiscono <strong>"Shadow AI"</strong> — non governati né tracciati dall'organizzazione, con rischi di esposizione dati e di non conformità all'AI Act.`,
  governance:`Riguarda le regole su quali dati aziendali possono essere condivisi con strumenti AI esterni. Senza policy chiare, i dipendenti possono inavvertitamente esporre dati riservati, segreti commerciali o dati personali di clienti a sistemi di terze parti.`,
  skills:`Valuta se il personale sa usare l'AI in modo produttivo, sicuro e con verifica critica degli output. <span class="info-norm">L'Art. 4 dell'AI Act, in vigore dal 2 febbraio 2025, richiede che le aziende che utilizzano sistemi di AI garantiscano un adeguato livello di alfabetizzazione AI del personale.</span>`,
  processes:`Indica quanto i processi aziendali sono documentati e strutturati: è un prerequisito per poterli affidare in parte all'AI. Processi non mappati sono difficili da automatizzare in modo affidabile e sicuro.`,
  compliance:`Misura quanto l'azienda è allineata agli obblighi del Regolamento UE 2024/1689 (AI Act): alfabetizzazione del personale, governance degli strumenti, trasparenza nell'uso. La non conformità può comportare sanzioni e, soprattutto, rischi operativi e reputazionali.`
};

function buildDims(){
  const col=document.getElementById("dims-col");col.innerHTML="";
  DIMS.forEach((d,i)=>{
    const c=document.createElement("div");c.className="dim-card";c.id="dc"+i;
    const infoHtml=INFOS[d.key]?`<button type="button" class="info-trigger" aria-label="Maggiori informazioni su ${d.name}" data-info-target="info-${d.key}">i</button>`:"";
    const popoverHtml=INFOS[d.key]?`<div class="info-popover" id="info-${d.key}" role="tooltip">${INFOS[d.key]}</div>`:"";
    c.innerHTML=`<div class="dim-header"><div class="dim-num">${i+1}</div><div><div class="dim-name">${d.name}${infoHtml}</div><div class="dim-desc">${d.desc}</div></div></div>
    ${popoverHtml}
    <div class="level-display"><div class="level-score" id="ls${i}">—</div><div class="level-text" id="lt${i}">Muovi il cursore per valutare</div></div>
    <div class="slider-wrap"><input type="range" id="sl${i}" min="1" max="4" step="1" value="1" style="opacity:.35" oninput="onSlide(${i},this.value)">
    <div class="ticks"><span>1 — Assente</span><span>2 — Iniziale</span><span>3 — In sviluppo</span><span>4 — Strutturato</span></div></div>
    <div class="note-area"><label>Nota (opzionale)</label><textarea placeholder="Descrivi brevemente la tua situazione..." id="nt${i}" rows="2" maxlength="5000"></textarea></div>`;
    col.appendChild(c);
  });
}

function onSlide(i,v){
  const val=parseInt(v);scores[i]=val;
  const col=LC[val-1];
  document.getElementById("ls"+i).textContent=val;
  document.getElementById("ls"+i).style.color=col;
  document.getElementById("lt"+i).textContent=LB[val-1]+" — "+DIMS[i].levels[val-1];
  document.getElementById("lt"+i).style.color="var(--text)";
  document.getElementById("sl"+i).style.opacity="1";
  document.getElementById("dc"+i).style.borderColor=col+"66";
  update();
}

function drawRadar(){
  const canvas=document.getElementById("radar");
  const ctx=canvas.getContext("2d");
  const W=canvas.width,H=canvas.height,cx=W/2,cy=H/2,R=Math.min(W,H)/2-30;
  const n=5;const angles=Array.from({length:n},(_,i)=>(i*2*Math.PI/n)-Math.PI/2);
  ctx.clearRect(0,0,W,H);
  for(let r=1;r<=4;r++){
    const frac=r/4;ctx.beginPath();
    angles.forEach((a,i)=>{const x=cx+Math.cos(a)*R*frac,y=cy+Math.sin(a)*R*frac;i===0?ctx.moveTo(x,y):ctx.lineTo(x,y)});
    ctx.closePath();ctx.strokeStyle=r===4?"rgba(85,177,174,.55)":"rgba(0,0,0,.08)";ctx.lineWidth=r===4?1.5:1;ctx.stroke();
    ctx.fillStyle="rgba(61,139,136,.7)";ctx.font="10px Calibri,Trebuchet MS,sans-serif";ctx.textAlign="center";ctx.fillText(r,cx+6,cy-R*frac+4);
  }
  angles.forEach(a=>{ctx.beginPath();ctx.moveTo(cx,cy);ctx.lineTo(cx+Math.cos(a)*R,cy+Math.sin(a)*R);ctx.strokeStyle="rgba(0,0,0,.08)";ctx.lineWidth=1;ctx.stroke()});
  angles.forEach((a,i)=>{
    const x=cx+Math.cos(a)*(R+19),y=cy+Math.sin(a)*(R+19);
    ctx.fillStyle="rgba(31,41,55,.75)";ctx.font="bold 10px Calibri,Trebuchet MS,sans-serif";ctx.textAlign="center";ctx.textBaseline="middle";ctx.fillText(SH[i],x,y);
  });
  if(!scores.some(s=>s>0))return;
  ctx.beginPath();
  angles.forEach((a,i)=>{const frac=(scores[i]||0)/4;const x=cx+Math.cos(a)*R*frac,y=cy+Math.sin(a)*R*frac;i===0?ctx.moveTo(x,y):ctx.lineTo(x,y)});
  ctx.closePath();ctx.fillStyle="rgba(85,177,174,.18)";ctx.fill();
  ctx.strokeStyle="rgba(85,177,174,.85)";ctx.lineWidth=2.5;ctx.stroke();
  angles.forEach((a,i)=>{
    const s=scores[i]||0;if(!s)return;
    const frac=s/4;const x=cx+Math.cos(a)*R*frac,y=cy+Math.sin(a)*R*frac;
    ctx.beginPath();ctx.arc(x,y,5,0,Math.PI*2);ctx.fillStyle=LC[s-1];ctx.fill();
    ctx.strokeStyle="#FFFFFF";ctx.lineWidth=2.5;ctx.stroke();
  });
}

// Backend-aligned recommendation (PRIMUS <=9, CONSILIUM <=14, INITIUM otherwise)
function inferCourse(total){
  if(total<=9)return {course:"PRIMUS",desc:"Corso introduttivo di 4 ore: fondamenti AI generativa e obbligo di alfabetizzazione AI (Art. 4 EU AI Act).",color:"#e09f35"};
  if(total<=14)return {course:"CONSILIUM",desc:"Workshop strategico di 7 ore per board e dirigenti: AI Usage Policy, roadmap 90 giorni, progetti pilota.",color:"#55B1AE"};
  return {course:"INITIUM",desc:"Percorso operativo di 20 ore (5 moduli) con certificazione: prompt engineering, governance, uso sicuro degli strumenti.",color:"#3A8C89"};
}

function updateTotal(){
  const total=scores.reduce((s,v)=>s+v,0);const filled=scores.filter(v=>v>0).length;
  if(!filled){document.getElementById("tot-score").textContent="—";const b=document.getElementById("prof-badge");b.textContent="Compila l'assessment →";b.style.cssText="";document.getElementById("reco-block").style.display="none";return}
  document.getElementById("tot-score").textContent=filled<5?total+"*":total;
  const reco=inferCourse(total);
  const b=document.getElementById("prof-badge");b.textContent=filled<5?"Compilazione in corso...":"Profilo completo";b.style.background=reco.color+"22";b.style.color=reco.color;b.style.borderColor=reco.color+"55";
  if(filled>=3){const rb=document.getElementById("reco-block");rb.style.display="block";document.getElementById("reco-course").textContent=reco.course;document.getElementById("reco-desc").textContent=reco.desc}
}

function updateSummary(){
  const filled=scores.filter(v=>v>0).length;
  const sec=document.getElementById("summary-sec");sec.style.display=filled>=3?"block":"none";
  const subSec=document.getElementById("submit-sec");subSec.style.display=filled>=3?"block":"none";
  document.getElementById("btn-submit").disabled=filled<5;
  if(filled<3)return;
  const sg=document.getElementById("sum-grid");sg.innerHTML="";
  DIMS.forEach((d,i)=>{const s=scores[i];const col=s?LC[s-1]:"var(--muted)";const el=document.createElement("div");el.className="sum-item";el.innerHTML=`<div class="sum-score" style="color:${col}">${s||"—"}</div><div class="sum-name">${SH[i]}</div>`;sg.appendChild(el)});
  const gb=document.getElementById("gap-bars");gb.innerHTML="";
  DIMS.forEach((d,i)=>{const s=scores[i]||0;const pct=(s/4)*100;const col=s?LC[s-1]:"#444";
    const row=document.createElement("div");row.className="gap-row";
    row.innerHTML=`<div class="gap-name">${SH[i]}</div><div class="gap-track"><div class="gap-fill" style="width:0;background:${col}"></div></div><div class="gap-lbl" style="color:${col}">${s?s+"/4":"—"}</div>`;
    gb.appendChild(row);setTimeout(()=>row.querySelector(".gap-fill").style.width=pct+"%",60+i*70);
  });
}

function update(){drawRadar();updateTotal();updateSummary()}

// ---------- Lead form gating ----------
function setFieldError(name,msg){
  const input=document.getElementById(name);
  const err=document.querySelector(`.field-error[data-for="${name}"]`);
  if(input)input.classList.toggle("has-error",!!msg);
  if(err){err.textContent=msg||"";err.classList.toggle("show",!!msg)}
}

function clearAllFieldErrors(){
  document.querySelectorAll(".field-error").forEach(e=>{e.textContent="";e.classList.remove("show")});
  document.querySelectorAll("input.has-error").forEach(e=>e.classList.remove("has-error"));
}

function validateLeadFormClient(){
  clearAllFieldErrors();
  let ok=true;
  const required=[
    ["first_name","Inserisci il tuo nome."],
    ["last_name","Inserisci il tuo cognome."],
    ["company_name","Inserisci il nome dell'azienda."],
    ["email","Inserisci la tua email aziendale."]
  ];
  required.forEach(([name,msg])=>{
    const v=document.getElementById(name).value.trim();
    if(!v){setFieldError(name,msg);ok=false}
  });
  const email=document.getElementById("email").value.trim();
  if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){setFieldError("email","Email non valida.");ok=false}
  if(!document.getElementById("gdpr_consent").checked){
    setFieldError("gdpr_consent","Devi accettare il trattamento dei dati per proseguire.");
    ok=false;
  }
  return ok;
}

document.getElementById("btn-start").addEventListener("click",()=>{
  if(!validateLeadFormClient()){
    document.querySelector(".has-error")?.scrollIntoView({behavior:"smooth",block:"center"});
    return;
  }
  document.getElementById("assessment-sec").style.display="block";
  setTimeout(()=>document.getElementById("assessment-sec").scrollIntoView({behavior:"smooth",block:"start"}),100);
});

// ---------- Submit AJAX ----------
function showSubmitError(msg){
  const a=document.getElementById("submit-error");
  a.textContent=msg;a.classList.add("show");
  a.scrollIntoView({behavior:"smooth",block:"center"});
}
function hideSubmitError(){document.getElementById("submit-error").classList.remove("show")}

function setSubmitting(on){
  const btn=document.getElementById("btn-submit");
  btn.disabled=on || scores.filter(v=>v>0).length<5;
  const label=btn.querySelector(".btn-label");
  if(on){label.innerHTML='<span class="spinner"></span> Invio in corso...'}
  else{label.textContent="Ricevi il report PDF"}
}

document.getElementById("lead-form").addEventListener("submit",async (e)=>{
  e.preventDefault();
  hideSubmitError();

  if(!validateLeadFormClient()){
    showSubmitError("Controlla i dati del form in cima alla pagina.");
    return;
  }
  if(scores.filter(v=>v>0).length<5){
    showSubmitError("Compila tutte e 5 le dimensioni prima di inviare.");
    return;
  }

  setSubmitting(true);

  const notes={};
  DIMS.forEach((d,i)=>{const v=document.getElementById("nt"+i).value.trim();notes[d.key]=v||null});
  const scoresObj={};
  DIMS.forEach((d,i)=>{scoresObj[d.key]=scores[i]});

  const payload={
    _token:document.querySelector('meta[name="csrf-token"]').content,
    first_name:document.getElementById("first_name").value.trim(),
    last_name:document.getElementById("last_name").value.trim(),
    email:document.getElementById("email").value.trim(),
    phone:document.getElementById("phone").value.trim()||null,
    company_name:document.getElementById("company_name").value.trim(),
    role:document.getElementById("role").value.trim()||null,
    gdpr_consent:document.getElementById("gdpr_consent").checked,
    scores:scoresObj,
    notes:notes,
    website:document.getElementById("website").value
  };

  try{
    const res=await fetch("{{ route('assessment.submit') }}",{
      method:"POST",
      headers:{
        "Content-Type":"application/json",
        "Accept":"application/json",
        "X-Requested-With":"XMLHttpRequest",
        "X-CSRF-TOKEN":payload._token
      },
      body:JSON.stringify(payload),
      credentials:"same-origin",
      redirect:"follow"
    });

    if(res.redirected){
      window.location.href=res.url;
      return;
    }
    if(res.ok){
      window.location.href="{{ route('assessment.thanks') }}";
      return;
    }
    if(res.status===422){
      const data=await res.json().catch(()=>({}));
      const errs=data.errors||{};
      let firstMsg=null;
      Object.keys(errs).forEach(k=>{
        const flat=k.includes(".")?k.split(".")[0]:k;
        const msg=Array.isArray(errs[k])?errs[k][0]:errs[k];
        if(["first_name","last_name","email","company_name","role","phone","gdpr_consent"].includes(flat)){
          setFieldError(flat,msg);
        }
        if(!firstMsg)firstMsg=msg;
      });
      showSubmitError(firstMsg || "Controlla i dati inseriti e riprova.");
      return;
    }
    if(res.status===429){
      showSubmitError("Hai già inviato il modulo recentemente. Riprova tra qualche minuto.");
      return;
    }
    showSubmitError("C'è stato un problema tecnico. Il team Noscite è stato avvisato e ti contatteremo entro 24 ore. Per assistenza scrivici a sales@noscite.it");
  }catch(err){
    showSubmitError("Connessione interrotta. Verifica la rete e riprova. Se il problema persiste scrivici a sales@noscite.it");
  }finally{
    setSubmitting(false);
  }
});

buildDims();drawRadar();

/* === Info popover toggle (Marketing-03) === */
(function(){
  let openPopover=null;
  function closeOpen(){
    if(openPopover){openPopover.classList.remove("open");openPopover=null}
  }
  // Listener delegato: copre i trigger creati dinamicamente da buildDims().
  document.addEventListener("click",function(e){
    const trigger=e.target.closest(".info-trigger");
    if(trigger){
      e.preventDefault();e.stopPropagation();
      const targetId=trigger.getAttribute("data-info-target");
      const popover=document.getElementById(targetId);
      if(!popover)return;
      if(popover===openPopover){closeOpen();return}
      closeOpen();
      popover.classList.add("open");
      openPopover=popover;
      return;
    }
    // click fuori dal popover aperto → chiudi
    if(openPopover && !openPopover.contains(e.target)){closeOpen()}
  });
  document.addEventListener("keydown",function(e){
    if(e.key==="Escape"){closeOpen()}
  });
})();
</script>
</body>
</html>
