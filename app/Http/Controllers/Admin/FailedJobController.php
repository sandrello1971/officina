<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Visibilità sui job asincroni falliti (tabella failed_jobs). Elenco con classe
 * job + messaggio d'eccezione, retry (singolo o tutti) e rimozione. Usa la
 * machinery Laravel (queue:retry / queue:forget).
 *
 * I job falliti di tutti gli enti stanno nello stesso store (DB central): ogni
 * ente vede e tocca solo i propri, riconosciuti dal tenant_id nel payload.
 */
class FailedJobController extends Controller
{
    public function index()
    {
        $jobs = $this->ownJobs()
            ->orderByDesc('failed_at')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $payload = json_decode($row->payload, true) ?: [];
                $exc = (string) $row->exception;
                return (object) [
                    'uuid'       => $row->uuid,
                    'queue'      => $row->queue,
                    'name'       => $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'job'),
                    'exception'  => strtok($exc, "\n"),        // prima riga = messaggio
                    'failed_at'  => $row->failed_at,
                ];
            });

        return view('admin.failed-jobs.index', compact('jobs'));
    }

    public function retry(string $uuid)
    {
        abort_unless($this->ownJobs()->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        return back()->with('success', 'Job rimesso in coda.');
    }

    public function retryAll()
    {
        $uuids = $this->ownJobs()->pluck('uuid')->all();
        if ($uuids) {
            Artisan::call('queue:retry', ['id' => $uuids]);
        }
        return back()->with('success', 'Tutti i job falliti rimessi in coda.');
    }

    public function forget(string $uuid)
    {
        abort_unless($this->ownJobs()->where('uuid', $uuid)->exists(), 404);
        Artisan::call('queue:forget', ['id' => $uuid]);
        return back()->with('success', 'Job fallito rimosso.');
    }

    public function flush()
    {
        $this->ownJobs()->delete();
        return back()->with('success', 'Tutti i job falliti eliminati.');
    }

    /** Job falliti dell'ente corrente; il primario vede anche quelli pre-tenancy (senza tenant_id). */
    private function ownJobs(): Builder
    {
        $query = DB::connection(config('queue.failed.database'))->table(config('queue.failed.table', 'failed_jobs'));
        $tenant = tenant();

        if ($tenant === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($tenant) {
            $q->whereRaw("(payload::jsonb)->>'tenant_id' = ?", [$tenant->getTenantKey()]);
            if ($tenant->isPrimary()) {
                $q->orWhereRaw("(payload::jsonb)->>'tenant_id' IS NULL");
            }
        });
    }
}
