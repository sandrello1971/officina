<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Allinea (o verifica) il DB template da cui viene clonato ogni nuovo tenant.
 *
 * Perché esiste un comando dedicato invece della riga documentata
 * `DB_DATABASE=officina_tpl php artisan migrate --path=...`:
 *
 * quella riga NON funziona quando `config:cache` è attivo — cioè sempre, in
 * produzione. Il file di cache congela `database.connections.pgsql.database`,
 * le chiamate a env() non vengono più valutate e il comando gira sul database
 * applicativo invece che sul template. Il guaio è che non fallisce: sul DB
 * applicativo le migration ci sono già, quindi risponde "Nothing to migrate"
 * e sembra tutto a posto mentre il template resta indietro. È successo, e il
 * problema si è visto solo controllando a mano le colonne.
 *
 * Qui la connessione viene costruita a runtime con Config::set (che la cache
 * non può sovrascrivere) e il comando STAMPA il database su cui ha operato,
 * letto dal database stesso con current_database(). Se legge il nome sbagliato
 * si ferma: non c'è modo di allineare silenziosamente il DB sbagliato.
 *
 * Uso:
 *   php artisan tenant:template-migrate           # applica le migration mancanti
 *   php artisan tenant:template-migrate --check   # verifica soltanto (per il deploy/CI)
 */
class TenantTemplateMigrate extends Command
{
    protected $signature = 'tenant:template-migrate
        {--check : Non applica nulla: esce con errore se il template è indietro}';

    protected $description = 'Allinea il DB template dei nuovi tenant alle migration del repo.';

    /** Nome della connessione creata al volo per parlare col template. */
    private const CONNECTION = 'tenant_template_runtime';

    public function handle(): int
    {
        $template = config('tenancy.database.template_db');

        if (! $template) {
            $this->error('tenancy.database.template_db (TENANT_TEMPLATE_DB) non configurato.');

            return self::FAILURE;
        }

        $this->registerConnection($template);

        // Verifica che si stia parlando col database giusto, chiedendolo al
        // database. È il controllo che rende impossibile la modalità di
        // fallimento silenzioso descritta sopra.
        try {
            $effettivo = DB::connection(self::CONNECTION)->selectOne('select current_database() as db')->db;
        } catch (\Throwable $e) {
            $this->error("Non riesco a connettermi al template '{$template}': {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($effettivo !== $template) {
            $this->error("Atteso il template '{$template}', ma la connessione è su '{$effettivo}'. Interrompo.");

            return self::FAILURE;
        }

        $this->line("Template: <info>{$effettivo}</info>");

        $opzioni = [
            '--database' => self::CONNECTION,
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
        ];

        if ($this->option('check')) {
            $opzioni['--pretend'] = true;
        }

        Artisan::call('migrate', $opzioni, $this->output);

        $inSospeso = $this->pendingCount($template);

        if ($this->option('check')) {
            if ($inSospeso > 0) {
                $this->error(
                    "Il template è indietro di {$inSospeso} migration: i tenant creati da ora "
                    .'nascerebbero con uno schema incompleto. Lancia `php artisan tenant:template-migrate`.'
                );

                return self::FAILURE;
            }

            $this->info('Template allineato al repo.');

            return self::SUCCESS;
        }

        // Dopo l'applicazione non deve restare nulla in sospeso: se resta,
        // qualcosa è fallito a metà e i tenant nuovi sarebbero incompleti.
        if ($inSospeso > 0) {
            $this->error("Restano {$inSospeso} migration non applicate al template.");

            return self::FAILURE;
        }

        $this->info('Template allineato.');

        return self::SUCCESS;
    }

    /**
     * Connessione al template ricavata da quella applicativa: stessi host,
     * credenziali e driver, solo il nome del database cambia.
     */
    private function registerConnection(string $template): void
    {
        $base = config('database.connections.'.config('database.default'));
        $base['database'] = $template;

        Config::set('database.connections.'.self::CONNECTION, $base);
        DB::purge(self::CONNECTION);
    }

    /** Migration presenti nel repo ma non nel ledger del template. */
    private function pendingCount(string $template): int
    {
        $applicate = DB::connection(self::CONNECTION)->table('migrations')->pluck('migration')->all();

        $suDisco = collect(glob(database_path('migrations/tenant/*.php')))
            ->map(fn (string $p) => basename($p, '.php'))
            ->all();

        return count(array_diff($suDisco, $applicate));
    }
}
