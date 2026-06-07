# Atheneum Schola — Fetta 1: schema dati e mappa rotte

> Bozza di progettazione, allineata alle convenzioni del codebase esistente:
> PK UUID con `gen_random_uuid()`, pgvector 1536 su `documents_rag`,
> CHECK constraint via `DB::statement`, soft delete dove serve, Laravel 12 / Postgres 17.

---

## 0. Stato reale RAG (verificato 06/06) — ⚠️ prerequisito del pacchetto 6

> **AGGIORNAMENTO 07/06 — prerequisito svolto (branch `schola/06pre-pgvector`).**
> pgvector installato e `CREATE EXTENSION vector` su **dev** e **test** (NON in
> prod: arriverà al deploy). `documents_rag.embedding = vector(768)` + indice
> **HNSW cosine**; embedding via videoai `/api/embeddings`
> (`paraphrase-multilingual-mpnet-base-v2`, 768d); retrieval vettoriale in
> `RagService` (coseno + soglia `schola.rag_min_similarity`) dietro i flag
> `rag_vector_enabled_schola` (default true) / `rag_vector_enabled_corsi`
> (default false). Backfill: `php artisan schola:backfill-embeddings`. Migrazione
> e codice degradano in sicurezza dove l'estensione manca (prod). Quanto sotto
> resta la cronaca dello stato pre-intervento.

Verifica diretta su server e codice (06/06): lo stato attuale **diverge** dall'assunto "pgvector 1536" citato sopra.

- **Retrieval attuale = keyword/ILIKE.** `RagService::search`, `searchScoped`, `searchForUser` filtrano con `ILIKE '%termine%'` su `content`/`title`. Nessun uso di embedding, operatori `<=>`/`vector_cosine`, soglie di similarità.
- **Nessun embedding in produzione.** Su questo VPS (PostgreSQL 17.7, unico cluster) **pgvector non è installato** (`pg_available_extensions` non lo elenca) e `documents_rag` **non ha colonna `embedding`** (né in `atheneum_db`, né nel dump in `atheneum_dev_db`). La migrazione di creazione tentava un `ALTER … ADD COLUMN embedding vector(1536)` in `try/catch` che — senza pgvector — abortiva la transazione e impediva la creazione della tabella (bug corretto il 06/06: vedi migrazione `…03_create_documents_rag_table`, ora non transazionale e con `ALTER` condizionato alla presenza reale dell'estensione).

**Conseguenza di prodotto.** Il **retrieval gate a soglia di similarità del §5** (vincolo AI NON negoziabile) e il **clustering per similarità delle domande scoperte del §8** **richiedono RAG vettoriale**, oggi assente. Diventa quindi **PREREQUISITO del pacchetto 6**, da svolgere in una **sessione dedicata prima** di esso:

1. Installazione `postgresql-17-pgvector` (richiede `sudo`).
2. `CREATE EXTENSION vector` su **dev**, **test** e **prod**.
3. Aggiunta colonna `embedding vector(1536)` + indice ivfflat su `documents_rag` (migrazione dedicata).
4. **Backfill** degli embedding dei chunk esistenti.
5. **Riscrittura del retrieval** in `RagService` (similarità coseno + soglia) mantenendo lo scope/ACL attuale.
6. CI da riportare su immagine **`pgvector/pgvector:pg17`** (oggi `postgres:17` semplice, in parità con prod).

Fino ad allora, ogni riferimento dello SPEC a embedding/soglia va letto come **target architetturale**, non stato corrente.

---

## 1. Decisioni di fondo recepite

- Onboarding **senza ente scuola**: il docente crea le classi, gli studenti entrano con codice invito (con approvazione opzionale). Le colonne `school_id` nascono nullable per la fase 2.
- **Ruolo nuovo `professor`** sul campo `students.role` (non si riusa `instructor`): interfacce, scope Minerva e permessi distinti dai formatori Atheneum.
- Ingestion fetta 1: **audio, YouTube, foto multiple (anche manoscritti), PDF scansionati**. OCR via Claude vision (nessuna dipendenza tesseract).
- Pipeline: `teaching_documents` (grezzo) → `teaching_artifacts` (lavorato) → `artifact_publications` (per classe) → indicizzazione RAG con scope classe.
- Le foto di pagine di libri restano confinate al perimetro docente→classe (nessuna condivisione tra docenti in fetta 1, anche per ragioni di copyright).

---

## 2. Migrazioni

### 2.1 Alterazioni a tabelle esistenti

#### `students` — estensione ruolo
```php
// 1) drop del CHECK esistente e ricreazione con 'professor'
DB::statement('ALTER TABLE students DROP CONSTRAINT students_role_check');
DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
    CHECK (role IS NULL OR role IN ('student', 'instructor', 'admin', 'professor'))");
```
Nota: gli **studenti delle scuole** restano `role = 'student'` (o NULL) come gli studenti
Atheneum; la distinzione di contesto la dà l'appartenenza a `class_students`,
non il ruolo. Evitiamo un ruolo `school_student` finché non serve davvero.

#### `documents_rag` — scope di classe
```php
Schema::table('documents_rag', function (Blueprint $table) {
    $table->foreignUuid('school_class_id')->nullable()
          ->constrained('school_classes')->cascadeOnDelete();
    $table->foreignUuid('teacher_id')->nullable()        // = students.id con role professor
          ->constrained('students')->cascadeOnDelete();
    $table->string('scope')->default('platform');
    $table->index(['school_class_id', 'scope']);
});
DB::statement("ALTER TABLE documents_rag ADD CONSTRAINT documents_rag_scope_check
    CHECK (scope IN ('platform', 'instructor_only', 'teacher_private', 'class'))");
```
Retro-compatibilità: i chunk esistenti restano `scope='platform'`
(o `instructor_only` dove `is_instructor_only=true` — backfill in migrazione).
A regime `is_instructor_only` diventa deprecato in favore di `scope`.

---

### 2.2 Tabelle nuove

#### `school_classes`
```php
Schema::create('school_classes', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->uuid('school_id')->nullable();              // fase 2, per ora sempre NULL
    $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
    $table->string('name');                             // "3ªB"
    $table->string('subject');                          // "Fisica" (stringa libera in fetta 1)
    $table->string('school_year', 9);                   // "2026/2027"
    $table->string('invite_code', 8)->unique();
    $table->boolean('invite_enabled')->default(true);
    $table->boolean('requires_approval')->default(true); // default prudente (minori)
    $table->boolean('is_archived')->default(false);
    $table->timestamps();
    $table->softDeletes();
    $table->index(['teacher_id', 'school_year']);
});
```

#### `class_students` (pivot con stato)
```php
Schema::create('class_students', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
    $table->string('status')->default('pending');       // pending | active | removed
    $table->timestamp('approved_at')->nullable();
    $table->timestamps();
    $table->unique(['school_class_id', 'student_id']);
});
DB::statement("ALTER TABLE class_students ADD CONSTRAINT class_students_status_check
    CHECK (status IN ('pending', 'active', 'removed'))");
```

#### `teaching_documents` (materiale grezzo del docente)
```php
Schema::create('teaching_documents', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
    $table->string('title');
    $table->string('source_type');     // audio | youtube | photos | pdf | docx | text
    $table->string('source_url')->nullable();          // per youtube
    $table->json('source_files')->nullable();          // path in storage privato; array per foto multiple ordinate
    $table->string('status')->default('pending');      // pending | processing | ready | failed
    $table->text('failure_reason')->nullable();
    $table->longText('extracted_text')->nullable();    // output trascrizione/OCR, markdown
    $table->json('extraction_meta')->nullable();       // durata audio, n. pagine, lingua, metodo (whisper|yt_transcript|vision), costi
    $table->string('subject')->nullable();             // materia (denormalizzata, comoda per filtri)
    $table->json('tags')->nullable();                  // tag liberi, pattern InstructorNote
    $table->timestamps();
    $table->softDeletes();
    $table->index(['teacher_id', 'status']);
});
DB::statement("ALTER TABLE teaching_documents ADD CONSTRAINT teaching_documents_source_check
    CHECK (source_type IN ('audio', 'youtube', 'photos', 'pdf', 'docx', 'text'))");
DB::statement("ALTER TABLE teaching_documents ADD CONSTRAINT teaching_documents_status_check
    CHECK (status IN ('pending', 'processing', 'ready', 'failed'))");
```
Storage: `storage/app/private/teaching-documents/{teacher_id}/{document_id}/…`
(stesso pattern dei manuali formatore: mai accessibile via URL diretto, solo controller).

#### `teaching_artifacts` (output lavorati)
```php
Schema::create('teaching_artifacts', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->foreignUuid('teaching_document_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
    $table->string('type');            // transcript | summary | mindmap | conceptmap | quiz | outline
    $table->string('title');
    $table->longText('content')->nullable();   // markdown/HTML; per mindmap = markdown markmap;
                                               // per conceptmap = JSON {nodes,edges}; per quiz vedi nota
    $table->foreignUuid('quiz_id')->nullable()->constrained('quizzes')->nullOnDelete();
    $table->string('status')->default('ready');    // generating | ready | failed
    $table->json('generation_meta')->nullable();    // modello, token, prompt version
    $table->boolean('shared_with_teachers')->default(false);  // visibile nella Biblioteca docenti
    $table->uuid('origin_artifact_id')->nullable();           // lineage fork (attribuzione)
    $table->string('subject')->nullable();                    // denormalizzato per ricerca in biblioteca
    $table->json('tags')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->index(['teacher_id', 'type']);
    $table->index(['shared_with_teachers', 'subject']);
});
DB::statement("ALTER TABLE teaching_artifacts ADD CONSTRAINT teaching_artifacts_type_check
    CHECK (type IN ('transcript', 'summary', 'mindmap', 'conceptmap', 'quiz', 'outline'))");
```
**Nota quiz**: si riusa l'infrastruttura `quizzes / quiz_questions / quiz_attempts`
esistente. Serve rendere `quizzes.module_id` nullable (oggi i quiz vivono sotto
i moduli corso) — migrazione dedicata:
```php
Schema::table('quizzes', function (Blueprint $table) {
    $table->uuid('module_id')->nullable()->change();
});
```
QuizGeneratorService va parametrizzato sulla sorgente testo (oggi legge `Module.content`).

#### `artifact_publications` (artefatto × classe, con permessi)
```php
Schema::create('artifact_publications', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->foreignUuid('teaching_artifact_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
    $table->boolean('students_can_generate')->default(true);  // auto-generazione lato studente
    $table->boolean('downloadable')->default(false);
    $table->timestamp('published_at')->useCurrent();
    $table->timestamps();
    $table->unique(['teaching_artifact_id', 'school_class_id']);
    $table->index(['school_class_id', 'published_at']);
});
```
Alla pubblicazione → job di ingestion RAG: chunk del contenuto in `documents_rag`
con `scope='class'`, `school_class_id`, `teacher_id`. Alla rimozione → delete dei chunk.

#### `student_artifact_views` (analytics minime fetta 1)
```php
Schema::create('student_artifact_views', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->foreignUuid('artifact_publication_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
    $table->timestamp('first_viewed_at');
    $table->timestamp('last_viewed_at');
    $table->integer('view_count')->default(1);
    $table->unique(['artifact_publication_id', 'student_id']);
});
```
I risultati quiz arrivano gratis da `quiz_attempts` esistente.
Le conversazioni Minerva con scope classe restano in `chat_conversations`
(+ colonna `school_class_id` nullable, vedi sotto) → audit log AI-minori incluso.

#### `chat_conversations` — scope classe
```php
Schema::table('chat_conversations', function (Blueprint $table) {
    $table->foreignUuid('school_class_id')->nullable()
          ->constrained('school_classes')->nullOnDelete();
});
```

#### `unanswered_questions` (domande fuori KB → segnale per il docente)
```php
Schema::create('unanswered_questions', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $table->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
    $table->text('question');
    $table->float('best_similarity')->nullable();   // miglior score sotto soglia (diagnostica)
    $table->string('status')->default('open');      // open | addressed | dismissed
    $table->timestamps();
    $table->index(['school_class_id', 'status']);
});
DB::statement("ALTER TABLE unanswered_questions ADD CONSTRAINT unanswered_questions_status_check
    CHECK (status IN ('open', 'addressed', 'dismissed'))");
```
Popolata da RagService quando il retrieval non supera la soglia per uno studente
di classe. Aggregata nel cruscotto docente (raggruppamento per similarità tra domande
→ "argomenti mancanti").

---

## 3. Mappa rotte

### 3.1 Area docente — prefix `/docente`, middleware `auth:student` + gate `role=professor`

```
GET    /docente                                        → DashboardController@index
                                                          (classi, ultimi documenti, attività recente)

# Classi
GET    /docente/classi                                 → ClassController@index
POST   /docente/classi                                 → ClassController@store
GET    /docente/classi/{class}                         → ClassController@show (roster + materiali pubblicati)
PATCH  /docente/classi/{class}                         → ClassController@update (nome, approvazione, archivio)
POST   /docente/classi/{class}/rigenera-codice         → ClassController@regenerateCode
PATCH  /docente/classi/{class}/studenti/{enrollment}   → ClassRosterController@update (approva/rimuovi)

# Materiali (documenti grezzi)
GET    /docente/materiali                              → TeachingDocumentController@index (filtri: tipo, materia, tag, stato)
POST   /docente/materiali                              → TeachingDocumentController@store
                                                          (multipart: audio/pdf/foto[] | url youtube | testo)
GET    /docente/materiali/{document}                   → TeachingDocumentController@show
                                                          (testo estratto, stato pipeline, artefatti)
PATCH  /docente/materiali/{document}                   → TeachingDocumentController@update (titolo, materia, tag, testo corretto)
DELETE /docente/materiali/{document}                   → TeachingDocumentController@destroy (soft)
GET    /docente/materiali/{document}/file/{index}      → TeachingDocumentController@downloadSource (storage privato)
GET    /docente/materiali/{document}/stato             → TeachingDocumentController@status (polling JSON pipeline)

# Generazione artefatti
POST   /docente/materiali/{document}/genera            → ArtifactGenerationController@store
                                                          {type: summary|mindmap|conceptmap|quiz|outline, options}
GET    /docente/artefatti/{artifact}                   → ArtifactController@show
PATCH  /docente/artefatti/{artifact}                   → ArtifactController@update (editing manuale del contenuto)
DELETE /docente/artefatti/{artifact}                   → ArtifactController@destroy
POST   /docente/artefatti/{artifact}/rigenera          → ArtifactGenerationController@regenerate

# Pubblicazione
POST   /docente/artefatti/{artifact}/pubblica          → PublicationController@store
                                                          {class_ids[], students_can_generate, downloadable}
DELETE /docente/pubblicazioni/{publication}            → PublicationController@destroy (ritira + pulizia RAG)

# Biblioteca docenti (condivisione tra professori, semantica fork)
GET    /docente/biblioteca                             → TeacherLibraryController@index
                                                          (artefatti condivisi: filtri materia/tipo/tag, ricerca)
GET    /docente/biblioteca/{artifact}                  → TeacherLibraryController@show (anteprima + attribuzione)
POST   /docente/biblioteca/{artifact}/duplica          → TeacherLibraryController@fork
                                                          (copia indipendente nella libreria del docente)
PATCH  /docente/artefatti/{artifact}/condivisione      → ArtifactSharingController@update
                                                          (on/off; bloccato per transcript da source_type photos/pdf
                                                           — vedi nota copyright §1)

# Cruscotto
GET    /docente/classi/{class}/attivita                → ClassActivityController@index
                                                          (viste per artefatto, risultati quiz, n. interazioni Minerva)
GET    /docente/classi/{class}/domande-scoperte        → UnansweredQuestionsController@index
                                                          (domande fuori KB, raggruppate per argomento)
PATCH  /docente/domande-scoperte/{question}            → UnansweredQuestionsController@update (addressed/dismissed)
```

### 3.2 Lato studente — estensioni a `/learn`

```
# Iscrizione con codice
GET    /learn/classi/unisciti                          → ClassJoinController@create
POST   /learn/classi/unisciti                          → ClassJoinController@store {invite_code}
                                                          → stato pending o active a seconda della classe

# Fruizione
GET    /learn/classi                                   → StudentClassController@index
GET    /learn/classi/{class}                           → StudentClassController@show (feed cronologico pubblicazioni)
GET    /learn/classi/{class}/artefatti/{publication}   → StudentArtifactController@show
                                                          (registra/aggiorna student_artifact_views)
POST   /learn/classi/{class}/artefatti/{publication}/genera
                                                       → StudentGenerationController@store
                                                          (solo se students_can_generate; tipi: mindmap|quiz di autoverifica)

# Minerva scope classe: rotte chat esistenti, payload con school_class_id opzionale
# → RagService filtra scope='class' AND school_class_id IN (classi attive dello studente)
```

### 3.3 Admin — estensioni minime a `/admin`

```
PATCH  /admin/students/{student}/role                  → esistente, aggiunge opzione 'professor'
GET    /admin/scuola/classi                            → vista sola lettura classi/docenti (supporto)
```

---

## 4. Pipeline asincrone (job Laravel)

| Job | Trigger | Cosa fa |
|---|---|---|
| `ExtractTeachingDocumentJob` | store materiale | dispatch per `source_type`: audio→videoai/Whisper; youtube→prima `youtube-transcript-api`, fallback yt-dlp+Whisper; photos/pdf scansionato→Claude vision pagina per pagina (con riassemblaggio ordinato); docx→pandoc; pdf testuale→estrazione diretta. Scrive `extracted_text` + `extraction_meta`, stato `ready/failed` |
| `GenerateArtifactJob` | richiesta docente/studente | invoca il servizio corrispondente (Summary nuovo; MindMap/ConceptMap/Quiz esistenti, parametrizzati su testo sorgente) |
| `IngestPublicationRagJob` | pubblicazione | chunking + embedding in `documents_rag` con scope classe |
| `PurgeWithdrawnPublicationJob` | ritiro pubblicazione | rimozione chunk RAG |

Estensione **noscite-videoai**: endpoint `POST /api/audio/transcribe` (file audio puro)
e `POST /api/youtube/transcribe` (url; transcript nativo se presente, altrimenti
yt-dlp → audio → Whisper). Stesso venv e servizio systemd.

---

## 5. Politica AI: RAG chiuso sulla KB del docente

Vincolo di prodotto: **Minerva risponde esclusivamente in base alla KB creata dal
docente** per le classi/lezioni pertinenti. Nessun fallback sulla conoscenza del modello.

**Scope per ruolo:**

| Utente | Corpus interrogabile |
|---|---|
| Studente di classe | solo chunk `scope='class'` delle sue classi con enrollment `active` |
| Docente | `scope='teacher_private'` (suoi materiali, anche non pubblicati) + `scope='class'` delle sue classi |
| Studente/formatore Atheneum | invariato (`platform` / `instructor_only`) — i due mondi non si toccano |

**Meccanica del vincolo (3 livelli):**
1. **Retrieval gate**: filtro SQL duro sullo scope + soglia minima di similarità coseno
   (configurabile in `settings`, da tarare empiricamente). Se nessun chunk supera la
   soglia, **il modello non viene chiamato**: risposta standard "non è nei materiali
   della classe, chiedilo al tuo docente" + log in `unanswered_questions`.
2. **Contratto nel system prompt**: rispondere solo dal contesto fornito; se il contesto
   è insufficiente, dichiararlo; vietato integrare con conoscenza generale del modello;
   ogni affermazione riconducibile a un artefatto citato (titolo + link).
3. **Citazioni obbligatorie in UI**: ogni risposta mostra le fonti (artefatti pubblicati);
   risposta senza fonti = bug.

**Il rifiuto come funzionalità**: le domande fuori KB alimentano il cruscotto
"domande scoperte" del docente — la mappa di ciò che manca nei materiali o non è
stato capito dalla classe. Feedback loop didattico assente in Classroom.

Quando in fetta 2 arriveranno le `lessons`, lo scope potrà restringersi alla singola
lezione ("interrogami solo sulla lezione del 12/3"). In fetta 1 lo studente può già
aprire la chat dal contesto di un singolo artefatto (pre-filtro sul documento sorgente).

---

## 6. Condivisione tra docenti — Biblioteca docenti (in perimetro)

- Condivisione a livello di **artefatto** con semantica **fork**: il collega duplica
  una copia indipendente nella propria libreria (`origin_artifact_id` per attribuzione).
  Nessun riferimento vivo: modifiche/cancellazioni dell'autore non toccano le copie.
- Visibilità: tutti i docenti della piattaforma (in assenza di ente scuola); con la
  fase 2 si aggiungerà la visibilità "solo la mia scuola".
- **Guardrail copyright**: condivisibili gli artefatti trasformativi (riassunti, mappe,
  quiz, schemi). I `transcript` di documenti `source_type IN ('photos','pdf')` non sono
  condivisibili (sarebbero distribuzione del testo del libro fotografato); restano nel
  perimetro docente→sua classe. Checkbox di responsabilità alla prima condivisione.

---

## 7. Fuori perimetro fetta 1 (esplicito)

- Ente scuola, SSO istituzionale, registro elettronico
- Programmazione annuale / lezioni sequenziate (`lessons` arriva in fetta 2)
- Compiti con consegna e correzione AI
- Agente proattivo (livello 3: spaced repetition, alert misconcezioni)
- Notifiche push/email oltre l'essenziale

---

## 8. Punti aperti — DECISIONI (06/06)

1. **Tracciamento completo**: il docente vede tutto ciò che fa lo studente, inclusi i
   quiz auto-generati (tentativi in `quiz_attempts`, generazioni, interazioni Minerva).
   Implicazione UX: trasparenza verso lo studente — l'interfaccia dichiara che
   l'attività è visibile al docente (necessario anche in ottica GDPR/minori: informativa chiara).
2. **Rate limit AI**: contatore giornaliero per studente (cache), soglie configurabili
   in `settings`. Confermato.
3. **`birth_date`** su `students`: subito, in fetta 1 (nullable a schema, obbligatorio
   alla registrazione via codice classe).
4. **Tabella `subjects` normalizzata subito** (seed: materie di licei/tecnici italiani).
   `school_classes.subject` e i campi denormalizzati su documenti/artefatti diventano
   `subject_id` FK. Possibilità per il docente di proporre materie mancanti (`is_custom`).

1. **Quiz studente auto-generati**: tentativi salvati in `quiz_attempts` visibili al docente, o "palestra privata" non tracciata? (Impatto motivazionale: se tutto è tracciato, lo studente non si esercita liberamente.) Proposta: quiz pubblicati dal docente → tracciati; auto-generati → privati.
2. **Limiti di consumo AI per studente** (rate limit su generazioni e Minerva): necessari da subito per controllo costi — proposta: contatore giornaliero per studente in cache, soglie configurabili in `settings`.
3. **Registrazione studenti minorenni**: in fetta 1 basta email + codice classe? O raccogliamo data di nascita per il flag minore già ora? (Consiglio: sì, campo `birth_date` nullable su `students`, costa zero e serve dopo.)
4. `subject` come stringa libera vs tabella `subjects` seed-ata (Fisica, Matematica, …): stringa libera in fetta 1, normalizzazione in fetta 2.
