<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// News AI — audit di ogni recupero settimanale (ricerca online + fetch news).
// Gemella di freshness_runs: registra stato, tempi, quante news trovate, eventuale errore.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_runs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('status', 20)->default('running'); // running | completed | failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->integer('items_found')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });

        DB::statement("ALTER TABLE news_runs ADD CONSTRAINT news_runs_status_check
            CHECK (status IN ('running', 'completed', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('news_runs');
    }
};
