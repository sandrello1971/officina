<?php

declare(strict_types=1);

namespace App\Tenancy;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;

/**
 * Crea il database di un tenant CLONANDO un DB template Postgres
 * (`CREATE DATABASE ... WITH TEMPLATE=<tpl>`) invece di partire da template0.
 *
 * Il template (config: tenancy.database.template_db) contiene già lo schema Officina
 * completo, le estensioni non-trusted (vector) e il
 * ledger delle migration → così il provisioning NON richiede privilegi di
 * superuser per-tenant e i CREATE EXTENSION non vanno rieseguiti.
 *
 * Vincolo Postgres: nessuna sessione può essere connessa al template durante la
 * clonazione. Il template è marcato datistemplate=true e non riceve connessioni
 * applicative, quindi in pratica è sempre libero.
 */
class PostgreSQLTemplateDatabaseManager extends PostgreSQLDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $name = $tenant->database()->getName();
        $template = config('tenancy.database.template_db');

        if (empty($template)) {
            // Fallback esplicito: senza template configurato non possiamo
            // garantire estensioni/schema → fallisci in modo rumoroso.
            throw new \RuntimeException(
                'tenancy.database.template_db non configurato: impossibile clonare il DB del tenant. '
                .'Esegui prima database/tenant-template/bootstrap-superuser.sh e imposta TENANT_TEMPLATE_DB.'
            );
        }

        return $this->database()->statement(
            "CREATE DATABASE \"{$name}\" WITH TEMPLATE=\"{$template}\""
        );
    }
}
