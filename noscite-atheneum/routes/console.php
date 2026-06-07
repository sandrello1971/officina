<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('atheneum:purge-deleted-notes')->dailyAt('03:00');
Schedule::command('exams:fail-stale')->everyFiveMinutes()->withoutOverlapping();

// Rete di recupero durevole del RAG vettoriale Schola: vettorizza ogni notte i
// chunk rimasti senza embedding (es. videoai giù al momento dell'ingestion).
Schedule::command('schola:backfill-embeddings')->dailyAt('03:30')->withoutOverlapping();
