# CLAUDE.md — noscite-atheneum

Guida per le sessioni di sviluppo su questo progetto (Laravel 12 / PostgreSQL 17).
Codice in inglese, commenti e UI in italiano.

---

## Modulo Schola

### Cos'è
Verticale per **scuole superiori** dentro Atheneum. Attori:
- **professor** — nuovo `role` su `students` (distinto da `instructor` dei corsi).
- **studenti di classe** — iscritti a una classe tramite codice invito.

Pipeline dei contenuti:
`teaching_documents` (grezzo: audio/PDF/foto/YouTube/testo) →
`teaching_artifacts` (lavorato: summary/mindmap/conceptmap/quiz/outline) →
`artifact_publications` (pubblicazione per classe) →
indicizzazione **RAG con scope `class`**.

Riferimento di progettazione: `docs/schola/SPEC.md` (leggere SEMPRE, inclusa la
**nota §0 sullo stato reale del RAG**).

### AMBIENTE — regole assolute
- Si lavora **SOLO** in `/home/noscite/noscite-websites/noscite-atheneum`.
  Un **branch per pacchetto** (`schola/<nome>`), merge su `main` a pacchetto chiuso.
- `/var/www/noscite-atheneum` è **PRODUZIONE**: mai modificarla, mai eseguirci
  comandi. `atheneum_db` è il **DB di produzione**: mai migrazioni né scritture.
- Sviluppo su **`atheneum_dev_db`**, test su **`atheneum_test_db`** (phpunit già
  configurato per pgsql). Staging: **https://dev.atheneum.noscite.it** (basic auth).
- Il `sudo` disponibile copre **solo** `chown`/`chmod`/`setfacl`. Per **nginx,
  certbot, apt, postgres** (creazione DB, estensioni, ecc.) **chiedere all'utente**.

### Convenzioni del codebase (rispettare SEMPRE)
- **PK `uuid`** con `gen_random_uuid()`, relazioni `foreignUuid`, **CHECK
  constraint via `DB::statement`** (vedi migrazioni esistenti).
- **Defense in depth a 3 livelli**: visibilità UI + `abort_unless` nel controller
  + file in `storage/app/private` (mai accessibili via URL diretto, sempre serviti
  da un controller).
- **Servizi AI**: pattern di `MindMapGenerationService` —
  `Http::post` su `https://api.anthropic.com/v1/messages`, modello
  `claude-sonnet-4-5`, gestione errori con `RuntimeException`, log; chiave da
  `config/services.php` (`ANTHROPIC_API_KEY`). Vedi anche
  `ConceptMapGenerationService`, `QuizGeneratorService`.
- **Lavoro asincrono**: job Laravel sulla tabella `jobs` esistente.
- **Lingua**: commenti e nomi UI in **italiano**, codice in **inglese**.
- **Ogni pacchetto si chiude con suite verde su `atheneum_test_db`**
  (`php artisan test`).

### Vincolo di prodotto AI (NON negoziabile)
Per gli **studenti di classe**, Minerva risponde **SOLO** da chunk
`documents_rag` con `scope='class'` delle loro **classi attive**, con
**retrieval gate**: se il retrieval non produce contesto sufficiente, il modello
**NON viene chiamato** e la domanda finisce in `unanswered_questions`.
Gli studenti di classe non devono MAI ricevere chunk `platform`/`instructor_only`/
`teacher_private` né di classi altrui.

### Stato reale RAG (oggi)
Il retrieval è **keyword/ILIKE** (`RagService::search*`), **pgvector NON è
installato** e `documents_rag` non ha colonna `embedding`. Il passaggio a **RAG
vettoriale** (installazione estensione, colonna `embedding`, backfill, riscrittura
retrieval con similarità+soglia, CI su `pgvector/pgvector:pg17`) è **PREREQUISITO
del pacchetto 6**, da svolgere in **sessione dedicata con sudo dell'utente**.
Dettagli: `docs/schola/SPEC.md` §0.

### Agente proattivo (imminente)
Ogni feature che produce dati di attività studente deve scrivere su **tabelle
interrogabili** (mai solo log), perché saranno input dell'agente. Le **aggregazioni**
vanno in **service dedicati riusabili** (es. `app/Services/Schola/...`), non inline
nei controller.

### Separazione dei mondi
Corsi/formatori Atheneum e Schola **non condividono** scope RAG né interfacce. Non
modificare i comportamenti esistenti di `/learn` per gli studenti dei corsi e di
`/admin`, se non dove esplicitamente richiesto.

### Debito tecnico noto
- **Broadcasting/Echo** incompleto — vedi `docs/tech-debt.md`.
- **`routes/auth.php`** è scaffolding Breeze **orfano** (non incluso da
  `bootstrap/app.php`, che instrada solo `routes/web.php`): l'app usa auth custom
  `admin.login`/`student.login`.
