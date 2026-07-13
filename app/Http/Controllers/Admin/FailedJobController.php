<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Visibilità sui job asincroni falliti (tabella failed_jobs). Elenco con classe
 * job + messaggio d'eccezione, retry (singolo o tutti) e rimozione. Usa la
 * machinery Laravel (queue:retry / queue:forget / queue:flush).
 */
class FailedJobController extends Controller
{
    public function index()
    {
        $jobs = DB::table('failed_jobs')
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
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        return back()->with('success', 'Job rimesso in coda.');
    }

    public function retryAll()
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        return back()->with('success', 'Tutti i job falliti rimessi in coda.');
    }

    public function forget(string $uuid)
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        return back()->with('success', 'Job fallito rimosso.');
    }

    public function flush()
    {
        Artisan::call('queue:flush');
        return back()->with('success', 'Tutti i job falliti eliminati.');
    }
}
