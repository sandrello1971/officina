#!/usr/bin/env bash
#
# Bootstrap UNA TANTUM del multi-tenant (da eseguire come SUPERUSER Postgres).
# ---------------------------------------------------------------------------
# Perché serve un superuser: lo schema di Officina usa l estensione NON-trusted
# vector (pgvector) che solo un superuser può
# installare. Le facciamo installare UNA VOLTA in un "DB template"; poi il
# ruolo applicativo — a cui diamo CREATEDB — provisiona i database dei tenant
# clonando quel template con `CREATE DATABASE ... TEMPLATE`, che copia schema
# ED estensioni senza bisogno di CREATE EXTENSION per ogni tenant.
#
# Dopo questo bootstrap, il provisioning di ogni nuovo tenant NON richiede più
# privilegi di superuser: lo fa l'app (comando `tenant:create`).
#
# USO (esempi):
#   # DEV
#   sudo -u postgres APP_ROLE=atheneum_user SOURCE_DB=atheneum_test_db \
#        TEMPLATE_DB=officina_tpl CENTRAL_DB=officina_central_dev \
#        bash bootstrap-superuser.sh
#
#   # PROD (source = DB di produzione attuale)
#   sudo -u postgres APP_ROLE=atheneum_user SOURCE_DB=atheneum_db \
#        TEMPLATE_DB=officina_tpl CENTRAL_DB=officina_central \
#        bash bootstrap-superuser.sh
#
set -euo pipefail

# NB: niente apostrofi nel messaggio di ${VAR:?...}. Un `'` apre una citazione che
# bash non chiude più e rompe il parsing di TUTTO il resto del file: lo script era
# committato con "dell'app" qui e non superava nemmeno `bash -n` — non poteva girare
# così com'è. (Il template di produzione è quindi nato da una copia modificata a mano:
# è la spiegazione meccanica del suo stato non tracciato.)
APP_ROLE="${APP_ROLE:?imposta APP_ROLE (ruolo Postgres applicativo, es. atheneum_user)}"
SOURCE_DB="${SOURCE_DB:?imposta SOURCE_DB (DB da cui estrarre lo schema)}"
TEMPLATE_DB="${TEMPLATE_DB:-officina_tpl}"
CENTRAL_DB="${CENTRAL_DB:-officina_central}"

echo "==> 1/5 Concedo CREATEDB al ruolo applicativo ($APP_ROLE)"
psql -v ON_ERROR_STOP=1 -c "ALTER ROLE \"$APP_ROLE\" CREATEDB;"

echo "==> 2/5 Creo il DB central ($CENTRAL_DB) di proprietà di $APP_ROLE (se assente)"
if ! psql -tAc "SELECT 1 FROM pg_database WHERE datname='$CENTRAL_DB';" | grep -q 1; then
  createdb -O "$APP_ROLE" "$CENTRAL_DB"
else
  echo "    (già esistente, salto)"
fi

echo "==> 3/5 Creo il DB template ($TEMPLATE_DB) di proprietà di $APP_ROLE + estensioni"
if psql -tAc "SELECT 1 FROM pg_database WHERE datname='$TEMPLATE_DB';" | grep -q 1; then
  echo "    Il template esiste già. Per rigenerarlo: sblocca e droppa prima:"
  echo "      psql -c \"UPDATE pg_database SET datistemplate=false WHERE datname='$TEMPLATE_DB';\""
  echo "      dropdb $TEMPLATE_DB"
  exit 1
fi
createdb -O "$APP_ROLE" "$TEMPLATE_DB"
psql -v ON_ERROR_STOP=1 -d "$TEMPLATE_DB" -c "
  CREATE EXTENSION IF NOT EXISTS vector;
"

echo "==> 4/5 Carico lo SCHEMA (solo struttura, no dati di business) da $SOURCE_DB nel template"
# SET ROLE al ruolo app PRIMA di caricare: così gli oggetti nascono di proprietà
# di $APP_ROLE (NON di postgres), altrimenti il ruolo app — e i tenant clonati —
# non potrebbero leggerli/scriverli. Le estensioni esistono già → i CREATE
# EXTENSION IF NOT EXISTS del dump sono no-op anche senza privilegi di superuser.
# ON_ERROR_STOP=1 (era 0): con 0 un caricamento PARZIALE dello schema passava
# inosservato e il DB veniva comunque marcato template al passo 5 — poi clonato in
# ogni tenant futuro. Un template incompleto è il tipo di guasto che non dà sintomi
# finché non manca una tabella a un cliente. Meglio fallire qui, rumorosamente.
#
# --no-comments è NECESSARIO insieme a ON_ERROR_STOP=1: il dump contiene
# `COMMENT ON EXTENSION vector`, che richiedono di
# ESSERE PROPRIETARI dell'estensione. Dopo il SET ROLE al ruolo app non lo siamo
# più, quindi psql si ferma su "must be owner of extension vector" al primo
# commento. Era questa la ragione pratica dell'ON_ERROR_STOP=0 — che però, per
# ignorare quell'errore innocuo, ingoiava anche tutti gli altri. Verificato su
# copia: senza --no-comments il carico si interrompe alla riga 33 del dump.
echo "    schema come $APP_ROLE…"
{ echo "SET ROLE \"$APP_ROLE\";"; pg_dump --schema-only --no-owner --no-privileges --no-comments "$SOURCE_DB"; } \
  | psql -v ON_ERROR_STOP=1 -d "$TEMPLATE_DB" >/dev/null
# Copio SOLO il ledger delle migration (tabella `migrations`): così un tenant
# clonato risulta "già migrato" e `tenants:migrate` applica solo le migration
# NUOVE aggiunte dopo la creazione del template (no ri-creazione di tabelle).
echo "    ledger migrations come $APP_ROLE…"
{ echo "SET ROLE \"$APP_ROLE\";"; pg_dump --data-only --no-owner --no-comments --table=public.migrations "$SOURCE_DB"; } \
  | psql -v ON_ERROR_STOP=1 -d "$TEMPLATE_DB" >/dev/null

# Il carico è andato a buon fine? Un template vuoto o senza ledger non deve MAI
# arrivare al passo 5: verrebbe marcato clonabile e propagato a ogni tenant futuro.
TABLES=$(psql -tAc "SELECT count(*) FROM pg_tables WHERE schemaname='public'" -d "$TEMPLATE_DB")
LEDGER=$(psql -tAc "SELECT count(*) FROM migrations" -d "$TEMPLATE_DB")
if [ "$TABLES" -lt 50 ] || [ "$LEDGER" -lt 50 ]; then
  echo "ABORT: template incompleto (tabelle=$TABLES, ledger=$LEDGER). NON lo marco come template."
  echo "       Ispeziona $TEMPLATE_DB e ricomincia: un template parziale si propaga a ogni tenant."
  exit 1
fi
echo "    caricato: $TABLES tabelle, $LEDGER voci di ledger"

echo "==> 5/5 Marco $TEMPLATE_DB come template Postgres (clonabile dal ruolo app)"
psql -v ON_ERROR_STOP=1 -c "UPDATE pg_database SET datistemplate=true WHERE datname='$TEMPLATE_DB';"

echo ""
echo "OK. Bootstrap completato:"
echo "  - $APP_ROLE ha ora CREATEDB"
echo "  - central DB:  $CENTRAL_DB (owner $APP_ROLE)"
echo "  - template DB: $TEMPLATE_DB (owner $APP_ROLE, datistemplate=true, con estensioni)"
echo ""
echo "Prossimo passo (lato app, NON serve superuser):"
echo "  php artisan migrate --force           # central: tenants/domains/platform_users"
echo "  php artisan tenant:create ...        # provisiona un tenant clonando il template"
