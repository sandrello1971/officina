# Noscite — Documentazione di Sistema

> Documentazione operativa dei siti Noscite e procedura di trasferimento su nuovo VPS.
> **Aggiornata**: aprile 2026.

---

## 📑 Indice

1. [Panoramica architetturale](#1-panoramica-architetturale)
2. [Stack tecnologico](#2-stack-tecnologico)
3. [Siti e applicazioni](#3-siti-e-applicazioni)
   - 3.1 [noscite.it — sito principale + intranet](#31-nosciteit--sito-principale--intranet)
   - 3.2 [atheneum.noscite.it — piattaforma LMS](#32-atheneumnosciteit--piattaforma-lms)
   - 3.3 [Servizi Python ausiliari](#33-servizi-python-ausiliari)
4. [Database](#4-database)
5. [Servizi systemd e cron](#5-servizi-systemd-e-cron)
6. [Repository git](#6-repository-git)
7. [Procedura di migrazione su nuovo VPS](#7-procedura-di-migrazione-su-nuovo-vps)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Panoramica architetturale

L'infrastruttura Noscite ospita **due siti web Laravel** + **due servizi Python** ausiliari su un singolo VPS.

```
┌─────────────────────────────────────────────────────────────────┐
│                        VPS Ubuntu 25.04                         │
│                                                                 │
│   ┌──────────────────────┐    ┌──────────────────────────────┐ │
│   │   nginx (80/443)     │    │       PostgreSQL 17          │ │
│   │   • noscite.it       │◄───┤   • noscite_db               │ │
│   │   • atheneum.…       │    │   • atheneum_db              │ │
│   └─────┬────────┬───────┘    └──────────────────────────────┘ │
│         │        │                                              │
│         ▼        ▼                                              │
│   ┌────────┐ ┌──────────────┐    ┌─────────────────────────┐  │
│   │ noscite│ │ atheneum     │    │  Servizi Python         │  │
│   │ -site  │ │ (Laravel 12) │    │  • videoai (FastAPI)    │  │
│   │(Laravel│ │              │    │    127.0.0.1:8001       │  │
│   │  12)   │ │              │    │  • pkm-agent (watcher)  │  │
│   └────┬───┘ └──────────────┘    └─────────────────────────┘  │
│        │                                                        │
│        └──► consuma /var/www/noscite-kb (Obsidian vault)       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Domini pubblici**:
- `noscite.it`, `www.noscite.it` → sito vetrina + intranet aziendale + admin + KB
- `atheneum.noscite.it` → piattaforma LMS (Atheneum)

Entrambi su HTTPS con certificati Let's Encrypt gestiti da Certbot.

---

## 2. Stack tecnologico

| Componente | Versione |
|---|---|
| OS | Ubuntu 25.04 |
| Nginx | 1.26.3 (Ubuntu) |
| PHP | 8.4.5 (php8.4-fpm) |
| Composer | 2.9.7 |
| PostgreSQL | 17.7 |
| Pandoc | 3.1.11.1 |
| Python | 3.x in `/home/noscite/venv/` |
| Laravel (entrambi i siti) | 12.x |
| Node.js | per asset compilation (se serve) |

**Path standard**:
- App web: `/var/www/<nome-app>/`
- Code repo dev: `/home/noscite/noscite-websites/` (clone GitHub)
- Working directories Python: `/home/noscite/noscite-pkm-agent/`, `/var/www/noscite-videoai/`
- Vault KB: `/var/www/noscite-kb/` (vault Obsidian, sync git ogni 10 min)
- venv Python condiviso: `/home/noscite/venv/`

---

## 3. Siti e applicazioni

### 3.1 noscite.it — sito principale + intranet

**Path**: `/var/www/noscite-site/`
**Database**: `noscite_db` (utente: `noscite_user`)
**Routes registrate**: 88
**Migrations**: 17

#### Sezioni del sito

| Area | URL | Funzione |
|---|---|---|
| **Vetrina pubblica** | `/`, `/chi-siamo`, `/servizi`, ecc. | Landing pages, blog, contatti |
| **Blog** | `/blog`, `/blog/{slug}` | Articoli pubblici |
| **Contatti** | `/contatti` (GET+POST) | Form contatto con email + DB save |
| **Intranet** | `/intranet/...` | Area riservata interna Noscite |
| **Admin** | `/nosciteadmin/...` | Pannello amministrazione (whitelist) |
| **Knowledge Base pubblica** | `/kb` | Documenti dal vault Obsidian |

#### Feature implementate

- **Auth Microsoft 365 SSO** (Azure AD via `laravel/socialite` + `socialiteproviders/microsoft`):
  - Intranet: tutti gli utenti `@noscite.it` autenticati Microsoft entrano
  - Admin: subset whitelist in `config/intranet.php`
  - **Auto-promote intranet → admin**: se un utente intranet con email in whitelist accede a `/nosciteadmin/auth`, viene promosso automaticamente senza re-auth Microsoft
  - Silent SSO: rimosso `prompt=select_account` per non forzare la selezione account a ogni login
- **Form contatti** con pre-popolamento via query string `?msg=...` (usato dalla CTA "Richiedi info Percorso AI" su Atheneum)
- **Blog** con admin CRUD (create/edit posts via `/nosciteadmin/blog`)
- **KB ingestion** automatica dal vault Obsidian (vedi `intranet_tools` + `kb_documents` + `documents`)
- **Newsletter signup** (tabella `newsletter_subscriptions`)
- **Business cards** digitali (tabella `business_cards`)
- **Tracking sessioni intranet** (tabella `intranet_sessions`)
- **Roles & permissions** (Spatie Laravel Permission, tabelle `roles`/`permissions`/`model_has_*`)

#### Tabelle DB (23)

```
blog_posts             intranet_sessions       password_reset_tokens
business_cards         intranet_tools          permissions
cache, cache_locks     intranet_servers        role_has_permissions
contact_messages       jobs, job_batches       roles
documents              failed_jobs             sessions
kb_documents           model_has_permissions   users
migrations             model_has_roles         newsletter_subscriptions
```

#### Login Microsoft — flusso

1. Utente clicca login intranet → `/intranet/auth/redirect` → Microsoft → callback `/intranet/auth/callback`
2. Email validata: deve essere `@noscite.it`
3. Sessione settata: `intranet_user` con `id, name, email, avatar`
4. Se l'utente clicca poi "Admin" e arriva su `/nosciteadmin/auth`:
   - Se `intranet_user` ha email in `config('intranet.admins')` → promote automatica → dashboard admin
   - Altrimenti → form login admin (anche solo bottone Microsoft alternativo)

---

### 3.2 atheneum.noscite.it — piattaforma LMS

**Path**: `/var/www/noscite-atheneum/`
**Database**: `atheneum_db` (utente: `atheneum_user`)
**Routes registrate**: 149
**Migrations**: 35

#### Funzionalità principali

##### A) Catalogo corsi
- 5 corsi pubblicati: **PRIMUS**, **CONSILIUM**, **INITIUM**, **STRUCTURA**, **AGENTI AI & MCP**
- Ogni corso ha: moduli ordinati, materials per modulo (PDF/canvas/video/link), quiz per modulo + esame finale, video introduttivi opzionali
- Pagine pubbliche per corso: `/primus`, `/consilium`, `/initium`, `/structura`, `/ai-agents-mcp`
- **Sezione "Il Percorso AI"** in homepage: 3 card commerciali (PRIMUS/CONSILIUM/INITIUM) con CTA "Richiedi info"
- Tabella commerciale 5 colonne (Servizio | Durata | Listino | Early Access | Target) — colonne prezzi nascoste con `display:none` (riattivabili)

##### B) Studenti — area `/learn`
- Login: email+password locale **OR** Microsoft 365 SSO (Azure AD)
- Dashboard `/learn/dashboard` con corsi assegnati e progress
- Pagina corso: lista moduli, video player, search semantica nei video
- Pagina modulo:
  - Content rich-text con **note ancorate per paragrafo**: ogni `<p>/<h2>/<h3>/<h4>` ha un'iconcina `+` sticky a margine, click → popover scrittura nota; FAB floating in alto-destra apre pannello laterale lista note
  - Materiali (PDF, canvas interattivi, video)
  - Quiz del modulo
  - Sezione **Manuale Formatore** (visibile solo a `role=instructor`) con sezioni linkate alle ancore del manuale
- Demo studente: account speciale `demo@atheneum.noscite.it` con scope limitato a Primus, no salvataggi persistenti

##### C) Quiz e certificazione
- Quiz multipli per modulo + 1 esame finale per corso
- Sblocco esame finale: dopo aver completato ≥70% dei moduli del corso
- Certificato PDF generato (via dompdf) + email via Mailable

##### D) Minerva — assistente AI conversazionale
- Bolla chat globale in basso-destra accessibile da ogni pagina logged-in
- Modello: Claude Sonnet 4.5 via Anthropic API
- **Modalità summary** (default, 60 parole) e **expand** (risposta dettagliata)
- **RAG** su `documents_rag`: cerca chunks rilevanti per la domanda
- **Role-aware**: se l'utente è `role=instructor`:
  - Scope esteso a tutti i corsi (no filtro per pivot)
  - Include chunks instructor-only (manuali formatore)
  - System prompt aggiunge sezione "🎓 Note per il formatore" alla risposta
- Search videos: `searchInCourses` su `documents_rag` con filter su course_id

##### E) Manuali Formatore (instructor-only)
- Upload `.docx` → conversione automatica HTML via **pandoc** → salvato in `storage/app/private/instructor-manuals/<slug>/` (privato, no accesso diretto)
- Splitting automatico in **sezioni navigabili** basato su H1 (con normalize che promuove `<p><strong>Capitolo X</strong></p>` a H1 per docx senza heading semantici)
- **Auto-mapping sezione → modulo** via priority-based regex (Modulo N / Lezione N / Blocco X / Capitolo N)
- **UI admin override** del mapping: dropdown per riassegnare manualmente sezioni a moduli + reset all'auto-mapping
- **Ingestion in RAG** con flag `is_instructor_only=true`: i chunks finiscono in `documents_rag` ma sono visibili solo a Minerva quando l'utente è instructor
- 4 manuali presenti: PRIMUS, CONSILIUM, INITIUM, STRUCTURA, AGENTI AI

##### F) Knowledge Base Formatore
- Note tipizzate (12 tipi: metafora, errore comune, caso aziendale, esercizio extra, ecc.)
- 3 livelli granularità: corso / modulo / sezione manuale formatore
- Tag liberi con autocomplete
- Editor markdown con toolbar + preview live + upload immagini
- Flag "condividi con altri formatori"
- Soft delete con cestino (purge automatico dopo 30 giorni)
- Accessibile da `/learn/knowledge-base` (instructor) e `/admin/knowledge-base` (admin, sola lettura)

##### G) Canvas interattivi
- Canvas A3 stampabili (HTML statici con `@media print`) per workshop in presenza
- 13 canvas totali distribuiti su 3 corsi: Primus (2), Consilium (6), AI-Agents-MCP (5)
- I 6 canvas Consilium sono **interattivi + autosave per studente**:
  - Tabella `student_canvas_data` (1 record per studente×canvas, payload JSON)
  - API: `GET/PATCH /learn/canvas/{material}/data`
  - Autosave debounced 700ms via fetch + CSRF cookie XSRF-TOKEN
  - Toolbar fissa con stato salvataggio + bottone Stampa
  - Stampa con dati compilati (CSS @media print pulisce sfondo righe)

##### H) Admin area `/admin`
- Login: email+password (config) **OR** Microsoft SSO con whitelist `config('atheneum.admins')`
- CRUD: corsi, moduli, materiali, quiz, studenti, manuali formatore
- Promozione studenti a `role=instructor/admin` (UI dedicata con guardrail anti-lockout)
- CHECK constraint Postgres su `students.role` (solo NULL/student/instructor/admin)
- Knowledge Base sola lettura admin con filtri per autore/cestino
- Analytics dashboard

##### I) Sicurezza role-aware (defense in depth)

**3 livelli di check** per ogni risorsa instructor-only:
1. UI: bottoni/box visibili solo se `role=instructor`
2. Controller: `abort_unless($student->role === 'instructor', 403)`
3. Storage: file in `storage/app/private/` (non accessibili via URL diretto, solo via controller download)

**System role**: campo `students.role` con CHECK constraint a livello DB.
**Job title** (ruolo aziendale): campo separato `students.job_title`, libero.

#### Tabelle DB (29)

```
cache, cache_locks            instructor_note_images       students
chat_conversations            instructor_notes             student_canvas_data
chat_messages                 instructor_manual_sections   student_course
contact_messages              jobs, job_batches            student_module_progress
courses                       materials                    student_notes
documents_rag                 migrations                   users
failed_jobs                   modules                      
                              newsletter_subscriptions     password_reset_tokens
                              quiz_answers                 quiz_attempts
                              quiz_questions               quizzes
                              sessions
```

#### Comandi Artisan custom

```bash
php artisan atheneum:import-instructor-manual <docx> --course=<slug> [--title=<title>]
    # Importa un manuale formatore (.docx) per un corso. Conversione pandoc → HTML.

php artisan atheneum:ingest-instructor-manuals
    # Indicizza nel RAG tutti i manuali formatore presenti come chunks instructor-only.

php artisan atheneum:split-instructor-manuals [--course=<slug>]
    # (Re-)spezza i manuali formatore in sezioni navigabili.

php artisan atheneum:purge-deleted-notes [--days=30] [--dry-run]
    # Cancella permanentemente note instructor nel cestino oltre N giorni.
    # Schedulato daily alle 03:00.
```

---

### 3.3 Servizi Python ausiliari

#### noscite-videoai

- **Path**: `/var/www/noscite-videoai/`
- **Servizio systemd**: `noscite-videoai.service`
- **Process**: `uvicorn backend.api.main:app --host 127.0.0.1 --port 8001`
- **Funzione**: backend FastAPI per video AI / RAG / chat sui contenuti video
- **Integrazione Atheneum**: chiamato via HTTP da `App\Services\VideoAIService` per ricerca semantica nei video dei corsi
- **venv**: `/home/noscite/venv/`

#### noscite-pkm-agent

- **Path**: `/home/noscite/noscite-pkm-agent/`
- **Servizio systemd**: `noscite-pkm-agent.service`
- **Process**: `python3 watcher.py` (filewatcher Obsidian)
- **Funzione**: monitora il vault `/var/www/noscite-kb/`, cataloga i nuovi file con AI, scrive metadata
- **Status**: `activating auto-restart` al momento della discovery → da verificare se è in stato funzionante stabile

#### noscite-kb (vault Obsidian)

- **Path**: `/var/www/noscite-kb/`
- **Sync**: `git pull origin main` ogni 10 min via cron `/home/noscite/sync-kb.sh`
- **Repository git separato** dal repo `noscite-websites`
- **Consumato da**: noscite-pkm-agent + Laravel noscite-site (per `/kb` pubblica)

---

## 4. Database

PostgreSQL 17, cluster `17-main`, listening su `127.0.0.1:5432`.

| DB | User | Tabelle | Scopo |
|---|---|---|---|
| `noscite_db` | `noscite_user` | 23 | noscite.it (intranet, blog, KB, contatti) |
| `atheneum_db` | `atheneum_user` | 29 | atheneum.noscite.it (LMS) |

**Backup automatici**: ⚠️ **non configurati** al momento (vedi raccomandazioni alla sezione 7.7).

**Backup manuale**:
```bash
sudo -u postgres pg_dump noscite_db > ~/backup_noscite_$(date +%Y%m%d).sql
sudo -u postgres pg_dump atheneum_db > ~/backup_atheneum_$(date +%Y%m%d).sql
```

**Restore**:
```bash
sudo -u postgres psql noscite_db < backup_noscite_YYYYMMDD.sql
```

---

## 5. Servizi systemd e cron

### Systemd

```bash
systemctl status nginx                    # Web server
systemctl status php8.4-fpm               # PHP FPM (socket /var/run/php/php8.4-fpm.sock)
systemctl status postgresql@17-main       # PostgreSQL
systemctl status noscite-videoai          # Backend video AI Python
systemctl status noscite-pkm-agent        # PKM Agent watcher
```

### Cron (`crontab -l` come utente `noscite`)

```cron
*/10 * * * * /home/noscite/sync-kb.sh >> /var/log/kb-sync.log 2>&1
* * * * *    cd /var/www/noscite-site && php artisan schedule:run >> /dev/null 2>&1
```

Il `php artisan schedule:run` su noscite-site è il scheduler Laravel attivo. Su atheneum, il scheduler è registrato in `routes/console.php` ma **non c'è una entry cron dedicata** — questo significa che `atheneum:purge-deleted-notes` non gira automaticamente. ⚠️ **Da fixare** durante migrazione (vedi 7.6).

### Schedule Laravel

- **noscite-site**: scheduling generico (verificare con `php artisan schedule:list` da `/var/www/noscite-site`)
- **noscite-atheneum**: `atheneum:purge-deleted-notes` daily 03:00 (necessita cron dedicato)

---

## 6. Repository git

Repository: **`github.com/Noscitedevteam/websites`**, branch `main`.

**Layout monorepo**:
```
noscite-websites/
├── DOCUMENTATION.md       (questo file)
├── noscite-atheneum/      (Laravel app, deploy → /var/www/noscite-atheneum)
├── noscite-site/          (Laravel app, deploy → /var/www/noscite-site)
└── noscite-pkm-agent/     (Python watcher, deploy → /home/noscite/noscite-pkm-agent)
```

**Workflow di deploy attuale (manuale)**:
1. Modifiche fatte direttamente in `/var/www/<app>/` (deploy)
2. Sync verso il repo locale via `rsync` con esclusioni (vedi sezione 7.4)
3. `git add` + commit + push verso GitHub

**Nessun CI/CD attivo**: deploy automatico da push **non è configurato**.

---

## 7. Procedura di migrazione su nuovo VPS

Guida step-by-step per trasferire l'intera infrastruttura su un nuovo VPS Ubuntu 24.04+ o 25.04.

### 7.0 Preparazione preliminare (sul vecchio VPS)

```bash
# 1. Backup database
sudo -u postgres pg_dump noscite_db    | gzip > ~/backup_noscite_$(date +%F).sql.gz
sudo -u postgres pg_dump atheneum_db   | gzip > ~/backup_atheneum_$(date +%F).sql.gz

# 2. Backup file di runtime (manuali formatore, immagini note, canvas data, ecc.)
sudo tar czf ~/backup_atheneum_storage.tar.gz \
  -C /var/www/noscite-atheneum/storage/app/private . \
  -C /var/www/noscite-atheneum/storage/app/public/instructor-manuals .

sudo tar czf ~/backup_site_storage.tar.gz \
  -C /var/www/noscite-site/storage/app .

# 3. Backup .env files (CONTENGONO SECRETS — trasferiscili in modo sicuro)
sudo cp /var/www/noscite-atheneum/.env ~/atheneum.env
sudo cp /var/www/noscite-site/.env ~/site.env
chmod 600 ~/atheneum.env ~/site.env

# 4. Backup config nginx + certbot
sudo tar czf ~/backup_nginx.tar.gz /etc/nginx/sites-available/ /etc/nginx/sites-enabled/
sudo tar czf ~/backup_certbot.tar.gz /etc/letsencrypt/

# 5. Backup config services systemd custom
sudo tar czf ~/backup_systemd.tar.gz \
  /etc/systemd/system/noscite-videoai.service \
  /etc/systemd/system/noscite-pkm-agent.service

# 6. Export crontab utente noscite
crontab -l > ~/backup_crontab.txt

# 7. Lista pacchetti PHP installati (per replicare estensioni)
dpkg -l | grep -E "php8\.4-" > ~/backup_php_packages.txt

# 8. Trasferisci tutti i backup al nuovo VPS via scp/rsync
# (su nuovo VPS lato ricezione)
# rsync -avz vecchio-vps:~/backup_*.tar.gz vecchio-vps:~/backup_*.sql.gz vecchio-vps:~/*.env vecchio-vps:~/backup_crontab.txt ~/migration/
```

### 7.1 Setup nuovo VPS — sistema base

Su Ubuntu 24.04/25.04 fresh install:

```bash
# Aggiorna sistema
sudo apt update && sudo apt upgrade -y

# Pacchetti base
sudo apt install -y curl wget git rsync unzip software-properties-common \
                    ca-certificates gnupg lsb-release ufw
                    
# Firewall
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable

# Crea utente di servizio
sudo adduser noscite
sudo usermod -aG sudo,www-data noscite
```

### 7.2 Installa dipendenze applicative

```bash
# PHP 8.4 + estensioni necessarie
sudo add-apt-repository ppa:ondrej/php -y    # se serve PPA per 8.4 su Ubuntu 24.04
sudo apt update
sudo apt install -y php8.4 php8.4-fpm php8.4-cli php8.4-pgsql php8.4-mbstring \
                    php8.4-xml php8.4-curl php8.4-zip php8.4-gd php8.4-intl \
                    php8.4-bcmath php8.4-tokenizer

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# PostgreSQL 17
sudo install -d /usr/share/postgresql-common/pgdg
sudo curl -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
  --fail https://www.postgresql.org/media/keys/ACCC4CF8.asc
sudo sh -c 'echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
sudo apt update
sudo apt install -y postgresql-17

# Nginx
sudo apt install -y nginx

# Certbot
sudo apt install -y certbot python3-certbot-nginx

# Pandoc (per conversione manuali formatore)
sudo apt install -y pandoc

# Python 3 + venv (per i servizi ausiliari)
sudo apt install -y python3 python3-venv python3-pip

# Node.js (se serve per build assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 7.3 Setup PostgreSQL

```bash
# Crea utenti e database
sudo -u postgres psql <<EOF
CREATE USER noscite_user WITH PASSWORD '<PASSWORD_DA_OLD_ENV>';
CREATE DATABASE noscite_db OWNER noscite_user;
CREATE USER atheneum_user WITH PASSWORD '<PASSWORD_DA_OLD_ENV>';
CREATE DATABASE atheneum_db OWNER atheneum_user;
EOF

# Restore dei dump
gunzip < ~/migration/backup_noscite_*.sql.gz | sudo -u postgres psql noscite_db
gunzip < ~/migration/backup_atheneum_*.sql.gz | sudo -u postgres psql atheneum_db

# Verifica
sudo -u postgres psql -d atheneum_db -c "SELECT COUNT(*) FROM students;"
sudo -u postgres psql -d noscite_db -c "SELECT COUNT(*) FROM users;"
```

### 7.4 Clone repository + deploy applicazioni

```bash
# Diventa utente noscite
sudo su - noscite

# Clone repo
cd /home/noscite
git clone https://github.com/Noscitedevteam/websites.git noscite-websites

# Deploy noscite-site
sudo mkdir -p /var/www/noscite-site
sudo chown noscite:www-data /var/www/noscite-site
rsync -av --delete --exclude='.git' /home/noscite/noscite-websites/noscite-site/ /var/www/noscite-site/

# Deploy noscite-atheneum
sudo mkdir -p /var/www/noscite-atheneum
sudo chown noscite:www-data /var/www/noscite-atheneum
rsync -av --delete --exclude='.git' /home/noscite/noscite-websites/noscite-atheneum/ /var/www/noscite-atheneum/

# Per ogni app
for APP in noscite-site noscite-atheneum; do
  cd /var/www/$APP
  composer install --no-dev --optimize-autoloader
  
  # Restore .env (dal backup)
  cp ~/migration/${APP##*-}.env .env       # nome esempio
  # OPPURE crea da .env.example e copia chiavi sensibili
  
  php artisan key:generate --no-interaction   # solo se .env non riporta APP_KEY
  php artisan storage:link
  php artisan migrate --force                 # solo se vuoi rifare migrations (NO se hai già fatto restore SQL)
  php artisan optimize
done

# Restore storage (file uploadati: manuali formatore, immagini note, ecc.)
sudo tar xzf ~/migration/backup_atheneum_storage.tar.gz -C /var/www/noscite-atheneum/storage/app/
sudo tar xzf ~/migration/backup_site_storage.tar.gz -C /var/www/noscite-site/storage/app/

# Permessi corretti su storage e cache
for APP in noscite-site noscite-atheneum; do
  sudo chown -R noscite:www-data /var/www/$APP/storage /var/www/$APP/bootstrap/cache
  sudo chmod -R 775 /var/www/$APP/storage /var/www/$APP/bootstrap/cache
done
```

### 7.5 Setup nginx + SSL

```bash
# Restore config nginx originali
sudo tar xzf ~/migration/backup_nginx.tar.gz -C /

# Restore certificati Let's Encrypt
sudo tar xzf ~/migration/backup_certbot.tar.gz -C /

# Verifica configurazione
sudo nginx -t

# Riavvia
sudo systemctl restart nginx

# IMPORTANTE: aggiorna DNS dei domini facendoli puntare al nuovo IP
#   noscite.it           → A    <new-vps-ip>
#   www.noscite.it       → A    <new-vps-ip>
#   atheneum.noscite.it  → A    <new-vps-ip>

# Dopo che i DNS si propagano (TTL ~5min - 24h), verifica HTTPS
curl -I https://noscite.it
curl -I https://atheneum.noscite.it

# Se i certificati sono scaduti (dipende da quando il vecchio VPS si è fermato):
sudo certbot renew --dry-run
sudo certbot renew      # renew se necessario
```

### 7.6 Servizi Python (opzionali ma raccomandati)

```bash
# Crea venv condiviso
sudo su - noscite
python3 -m venv ~/venv
source ~/venv/bin/activate

# Deploy noscite-videoai (clone separato — non è nel monorepo websites!)
# ⚠️ noscite-videoai NON è nel repo websites. Va clonato/copiato a parte:
sudo mkdir -p /var/www/noscite-videoai
sudo chown -R noscite:noscite /var/www/noscite-videoai
# rsync da vecchio VPS o clone se ha proprio repo
rsync -avz vecchio-vps:/var/www/noscite-videoai/ /var/www/noscite-videoai/

cd /var/www/noscite-videoai
~/venv/bin/pip install -r requirements.txt

# Deploy noscite-pkm-agent (è nel repo!)
# (già clonato in /home/noscite/noscite-websites/noscite-pkm-agent/)
ln -s /home/noscite/noscite-websites/noscite-pkm-agent /home/noscite/noscite-pkm-agent
~/venv/bin/pip install -r ~/noscite-pkm-agent/requirements.txt    # se esiste

# Deploy vault Obsidian
sudo mkdir -p /var/www/noscite-kb
sudo chown noscite:noscite /var/www/noscite-kb
cd /var/www/noscite-kb
git clone <repo-vault-obsidian> .   # repo separato, chiedere URL al PM

# Restore systemd services
sudo tar xzf ~/migration/backup_systemd.tar.gz -C /
sudo systemctl daemon-reload
sudo systemctl enable --now noscite-videoai
sudo systemctl enable --now noscite-pkm-agent
```

### 7.7 Cron + scheduler

```bash
# Restore crontab
crontab ~/migration/backup_crontab.txt

# Verifica
crontab -l

# RACCOMANDATO: aggiungi anche il scheduler Atheneum (manca al momento)
crontab -e
# Aggiungi:
* * * * * cd /var/www/noscite-atheneum && php artisan schedule:run >> /dev/null 2>&1

# Crea il file di sync KB se non esiste
cat > /home/noscite/sync-kb.sh <<'EOF'
#!/bin/bash
cd /var/www/noscite-kb
git pull origin main --quiet
EOF
chmod +x /home/noscite/sync-kb.sh
sudo touch /var/log/kb-sync.log
sudo chown noscite:noscite /var/log/kb-sync.log
```

### 7.8 Backup automatici (BIG TODO — non configurati al momento)

**Raccomandazione forte**: configurare backup automatico daily prima di andare in produzione.

Esempio script `/usr/local/bin/noscite-backup.sh`:

```bash
#!/bin/bash
DATE=$(date +%F_%H%M)
BACKUP_DIR=/var/backups/noscite
mkdir -p $BACKUP_DIR

# Database
sudo -u postgres pg_dump noscite_db    | gzip > $BACKUP_DIR/noscite_db_$DATE.sql.gz
sudo -u postgres pg_dump atheneum_db   | gzip > $BACKUP_DIR/atheneum_db_$DATE.sql.gz

# Storage applicativo
tar czf $BACKUP_DIR/atheneum_storage_$DATE.tar.gz \
  -C /var/www/noscite-atheneum/storage/app .

tar czf $BACKUP_DIR/site_storage_$DATE.tar.gz \
  -C /var/www/noscite-site/storage/app .

# Cleanup: tieni 7 daily + 4 weekly
find $BACKUP_DIR -name '*.gz' -mtime +30 -delete
```

Cron:
```
0 2 * * * /usr/local/bin/noscite-backup.sh
```

Per backup off-site, configurare push verso S3/B2/rsync.net o simile.

### 7.9 Smoke test post-migrazione

Lista di check rapidi dopo il deploy:

```bash
# 1. Servizi attivi
systemctl is-active nginx php8.4-fpm postgresql@17-main noscite-videoai noscite-pkm-agent

# 2. HTTP risponde
curl -I https://noscite.it
curl -I https://atheneum.noscite.it

# 3. DB query
cd /var/www/noscite-atheneum && php artisan tinker --execute="echo DB::connection()->getDatabaseName(); echo PHP_EOL; echo App\Models\Course::count();"

# 4. Storage permessi
ls -la /var/www/noscite-atheneum/public/storage    # symlink valid?
sudo -u www-data test -w /var/www/noscite-atheneum/storage/app && echo "writable OK"

# 5. Login flow
# Manuale: apri https://atheneum.noscite.it/learn/login → prova login con utente noto
# Manuale: apri https://noscite.it/intranet/auth → prova login Microsoft

# 6. Email send (test SMTP)
cd /var/www/noscite-atheneum && php artisan tinker --execute="Mail::raw('test', fn(\$m) => \$m->to('test@example.com')->subject('Test'));"

# 7. Pandoc
echo "# Test" | pandoc -f markdown -t html

# 8. Cron attivo
sudo grep CRON /var/log/syslog | tail -5
```

---

## 8. Troubleshooting

### Problema: 419 Page Expired su form

CSRF token mismatch. Solitamente:
- Cache view stale: `php artisan view:clear && php artisan cache:clear`
- Sessione non condivisa: verifica `SESSION_DRIVER=database` in `.env` e che la tabella `sessions` esista

### Problema: 500 Internal Server Error generico

```bash
tail -100 /var/www/<app>/storage/logs/laravel.log
sudo tail -50 /var/log/nginx/error.log
```

### Problema: file uploadati non scaricabili

Verifica:
```bash
ls -la /var/www/noscite-atheneum/public/storage      # deve essere symlink valido
php artisan storage:link                             # ricrea se rotto
```

### Problema: Microsoft SSO fallisce con `AADSTS50011: redirect URI mismatch`

Sull'app Azure AD (portale.azure.com), verifica che i Reply URLs includano:
- `https://noscite.it/intranet/auth/callback`
- `https://noscite.it/nosciteadmin/auth/callback`
- `https://atheneum.noscite.it/auth/microsoft/callback`
- `https://atheneum.noscite.it/admin/auth/microsoft/callback`

### Problema: Minerva non risponde

```bash
# Verifica chiave Anthropic
grep ANTHROPIC_API_KEY /var/www/noscite-atheneum/.env

# Test diretto API
curl -X POST https://api.anthropic.com/v1/messages \
  -H "x-api-key: <KEY>" \
  -H "anthropic-version: 2023-06-01" \
  -H "content-type: application/json" \
  -d '{"model":"claude-sonnet-4-5","max_tokens":50,"messages":[{"role":"user","content":"hi"}]}'

# Verifica logs
tail -100 /var/www/noscite-atheneum/storage/logs/laravel.log | grep -i minerva
```

### Problema: pandoc fallisce su upload manuale formatore

```bash
# Verifica installato
which pandoc && pandoc --version

# Test manuale
pandoc /tmp/test.docx -t html5 --wrap=none
```

### Problema: VideoAI non risponde

```bash
systemctl status noscite-videoai
journalctl -u noscite-videoai -n 50

# Riavvio
sudo systemctl restart noscite-videoai

# Test endpoint
curl http://127.0.0.1:8001/health
```

---

## 📞 Supporto e contatti

- **Repository**: https://github.com/Noscitedevteam/websites
- **Branch principale**: `main`
- **Email infra**: `info@noscite.it`

---

*Documento di sistema. Aggiornato in occasione di modifiche significative all'infrastruttura.*
