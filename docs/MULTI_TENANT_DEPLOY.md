# Officina multi-tenant: cutover e operatività

Officina ospita più enti. Ogni ente ha il **proprio database Postgres** (corsi, news, documenti, docenti, discenti, impostazioni e chiavi AI) ed è servito su `admin.<host>` e `learn.<host>`. Il DB **central** (`officina_central`) contiene solo l'elenco degli enti, i loro domini, gli operatori della console e i job falliti.

- **Effetto Glitch** è l'ente *primario* (tenant zero). Resta sul DB storico `atheneum_db`, registrato così com'è, senza copie. È l'unico che può avere il modulo **Scuola** e l'accesso break-glass da `.env`.
- **Un nuovo ente** nasce clonando il DB template `officina_tpl`, che contiene schema ed estensioni.
- **Console di piattaforma:** `https://platform.officina.effettoglitch.it`, con 2FA obbligatorio.

## Cutover (una tantum)

Tutti i comandi `artisan` girano come `noscite` in `/var/www/noscite-atheneum`. I passi marcati **root** richiedono sudo.

### 0. Prima di toccare qualcosa

```bash
# Codice presente solo in prod: il deploy lo cancellerebbe (vedi deriva luglio/settembre)
diff -rq /var/www/noscite-atheneum/app /home/noscite/noscite-websites/app
# …idem per config routes database resources

# Backup del DB di Effetto Glitch
pg_dump -Fc -h 127.0.0.1 -U atheneum_user atheneum_db > /home/noscite/backup/atheneum_db-pre-multitenant.dump
cp /var/www/noscite-atheneum/.env /var/www/noscite-atheneum/.env.bak.premultitenant
```

### 1. Bootstrap Postgres (**root**, come utente `postgres`)

Concede CREATEDB ad `atheneum_user`, crea `officina_central` e il template `officina_tpl` (schema di `atheneum_db`, estensione `vector`, ledger delle migration, nessun dato).

```bash
sudo -u postgres APP_ROLE=atheneum_user SOURCE_DB=atheneum_db \
     TEMPLATE_DB=officina_tpl CENTRAL_DB=officina_central \
     bash /home/noscite/noscite-websites/database/tenant-template/bootstrap-superuser.sh
```

### 2. `.env` di produzione

Aggiungi o modifica queste voci:

```dotenv
CENTRAL_DB_DATABASE=officina_central
TENANT_TEMPLATE_DB=officina_tpl
CENTRAL_DOMAINS=localhost,127.0.0.1
PLATFORM_DOMAIN=platform.officina.effettoglitch.it
APP_BASE_DOMAIN=officina.effettoglitch.it

# database → redis: con DB diversi per ente, cache/sessioni/coda "database"
# finirebbero nel DB sbagliato. La cache per ente usa i tag (redis obbligatorio).
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Redis è condiviso col CRM: prefisso e DB propri, altrimenti i due worker
# consumerebbero la stessa lista queues:default.
REDIS_PREFIX=officina_
REDIS_DB=2
REDIS_CACHE_DB=3
```

Tre conseguenze:
- Cambiare `SESSION_DRIVER` fa **uscire tutti** una volta.
- Prima del cambio, svuota la coda `database`: `php artisan queue:work database --stop-when-empty`.
- Dopo il cambio, lancia `php artisan config:clear`.

### 3. Deploy del codice

```bash
cd /home/noscite/noscite-websites && ./deploy-atheneum.sh
```

Lo script migra il central e poi **si ferma in manutenzione** con "nessun ente registrato". È il comportamento atteso al primo giro.

### 4. Registrazione di Effetto Glitch come ente primario

```bash
cd /var/www/noscite-atheneum
php artisan tenant:register-existing --name="Effetto Glitch" --slug=effettoglitch \
    --db=atheneum_db --host=officina.effettoglitch.it --primary --modules=all
php artisan tenant:list            # annota l'ID dell'ente
```

Lo storage dell'ente vive in `storage/tenant<ID>/`. Per il primario va collegato alle cartelle esistenti, altrimenti materiali, certificati e video risultano spariti:

```bash
ID=<id dell'ente>
mkdir -p storage/tenant$ID
ln -s ../app       storage/tenant$ID/app
ln -s ../framework storage/tenant$ID/framework
ln -s ../logs      storage/tenant$ID/logs
```

Rilancia poi `./deploy-atheneum.sh`: esegue `tenants:migrate`, allinea il template e riapre il sito.

### 5. nginx, DNS, servizi (**root**)

1. **Snippet `/etc/nginx/snippets/officina-app.conf`.** Aggiungi questo blocco **prima** del `location ~* \.(jpg|…)$`. Serve perché i file pubblici degli enti secondari (`/media/…`) passino da Laravel invece di essere cercati come statici in `public/`:
   ```nginx
   location ^~ /media/ {
       try_files $uri /index.php?$query_string;
   }
   ```
2. **DNS.** La zona `effettoglitch.it` è su Aruba, che **non applica il wildcard** `*.officina`. Ogni host va creato come record A esplicito → `51.83.73.186`: `platform.officina` per la console (già fatto, settembre 2026) e i due host di ogni ente (vedi *Nuovo ente*). Aruba impiega qualche minuto ad allineare i nodi: verifica con `dig +trace <host>`, perché una query diretta a un nameserver può colpire un nodo non ancora aggiornato.
3. **Console.** `sudo scripts/tenant-host.sh --single platform.officina.effettoglitch.it`
4. **Riavvio servizi.** Il worker deve ripartire con la coda redis:
   ```bash
   systemctl restart noscite-atheneum-queue noscite-atheneum-reverb
   systemctl reload php8.4-fpm nginx
   ```

### 6. Primo operatore della console

```bash
php artisan platform:create-admin stefano.andrello@gmail.com --name="Stefano Andrello"
```

Al primo accesso l'operatore configura il 2FA.

### 7. Verifica

- `https://learn.officina.effettoglitch.it` e `https://admin.officina.effettoglitch.it`: login, un corso, un certificato, `/scuola`.
- `php artisan tenant:list` deve mostrare Effetto Glitch con ★ e DB `atheneum_db`.
- `php artisan schedule:list` deve mostrare i job come `tenants:each …`.
- La console apre l'elenco enti con i consumi AI del mese.

### Rollback

`atheneum_db` riceve solo una colonna additiva (`ai_usage.key_source`). Per tornare indietro:
1. Ripristina `.env.bak.premultitenant`.
2. Lancia `./rollback-atheneum.sh`.
3. `systemctl restart noscite-atheneum-queue noscite-atheneum-reverb`.

I DB `officina_central` e `officina_tpl` si possono lasciare dove sono.

## Nuovo ente

1. **Creazione.** Dalla console (*Nuovo ente*) oppure da CLI:
   ```bash
   php artisan tenant:create --name="Ente Srl" --host=ente.officina.effettoglitch.it \
       --admin-email=admin@ente.it --ai-key-mode=both --ai-budget=50
   ```
   Clona il template, registra `admin.`/`learn.` e crea il primo admin con password temporanea.
2. **DNS.** In Aruba, zona `effettoglitch.it`, crea due record A → `51.83.73.186`: `admin.<ente>.officina` e `learn.<ente>.officina`. Facoltativo: `<ente>.officina`, che reindirizza a `learn.`. Per un dominio dell'ente, i record `admin.` e `learn.` li crea l'ente sul suo DNS.
3. **Pubblicazione degli host (root)**, quando `dig +trace` li risolve: `sudo scripts/tenant-host.sh ente.officina.effettoglitch.it`. Certbot fallisce se il DNS non è ancora propagato.
4. **Configurazione dell'ente.** L'admin dell'ente completa le Impostazioni: nome istanza, SMTP, chiave Anthropic se in modalità BYOK, logo. Il template del certificato si genera al primo attestato con logo e host dell'ente.

## Operatività

| Cosa | Comando |
|---|---|
| Elenco enti | `php artisan tenant:list` |
| Sospendi / riattiva | `php artisan tenant:status <slug> suspended\|active` (o dalla console) |
| Comando su ogni ente | `php artisan tenants:each "<comando>" [--module=…]` |
| Migrazioni | lo fa il deploy: `migrate` (central) + `tenants:migrate` + `tenant:template-migrate` |
| Consumi AI | `php artisan ai:usage --all-tenants --days=30` |
| Template indietro? | `php artisan tenant:template-migrate --check` |

**Chiave AI per ente** (`ai_key_mode`):
- `platform`: sempre la nostra chiave, soggetta al budget mensile.
- `tenant`: solo la chiave inserita dall'ente; senza chiave l'AI è bloccata.
- `both`: la chiave dell'ente se c'è, altrimenti la nostra.

Ogni chiamata registra in `ai_usage.key_source` quale chiave è stata usata.

**Moduli** (`config/modules.php`): `ai_chat`, `ai_news`, `freshness`, `gap_scout`, `course_generation`, `scuola`. Un modulo spento dà 404 sulle sue rotte, sparisce dai menu e non gira nello scheduler. `scuola` è accettato solo sull'ente primario.

## Perché è fatto così (trappole note)

- **Sessione legata all'ente** (`EnsureSessionBelongsToTenant`). Lo store redis è condiviso e gli id utente si ripetono fra DB. Senza questo legame, un cookie di A presentato sull'host di B autenticherebbe l'utente con lo stesso id in B.
- **Config per ente** (`TenantConfig`). SMTP, chiavi API e host degli URL vengono riapplicati a ogni cambio di ente, ripartendo dai valori `.env`. Altrimenti un worker di coda userebbe la chiave dell'ente precedente.
- **`{tenant_host}` nelle rotte.** Il parametro di dominio viene tolto prima dei controller (Laravel lo passerebbe come primo argomento). `URL::defaults` lo riempie con l'host dell'ente, così `route()` e `route:cache` funzionano come prima.
- **Job falliti nel central, filtrati per `tenant_id`.** Ogni ente vede, ritenta e cancella solo i propri.
- **Canali Reverb `t.<ente>.…`.** Reverb è unico e gli id si ripetono fra enti.
- **Test.** Un solo DB fa da central e da ente, con il bootstrapper del DB spento (le scritture resterebbero fuori dalla transazione di RefreshDatabase). L'isolamento reale fra DB diversi va verificato su enti veri dopo il bootstrap del punto 1.
