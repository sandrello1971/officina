<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metering delle chiamate AI (Anthropic) — append-only. Ogni chiamata via
 * ClaudeClient scrive qui token in/out, modello, costo stimato e il CONTESTO
 * (feature + tenant/corso/attore) per attribuire i costi.
 *
 * Tenant-forward: school_id è nullable e pronto per la multi-tenantizzazione
 * dell'intera piattaforma (oggi valorizzato solo dove il contesto scuola esiste).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->string('feature');                 // es. 'quiz.generate', 'manual.remap', 'freshness.verify'
            $table->string('model');
            $table->integer('tokens_in')->default(0);
            $table->integer('tokens_out')->default(0);
            $table->decimal('cost_usd', 12, 6)->nullable(); // null se il modello non è nel listino config
            $table->string('status')->default('ok');   // 'ok' | 'error'
            $table->string('error')->nullable();

            // Contesto / attribuzione (tutti nullable: il contesto non è sempre disponibile).
            $table->uuid('school_id')->nullable();
            $table->uuid('course_id')->nullable();
            $table->string('actor_type')->nullable();  // 'admin' | 'student' | 'system'
            $table->uuid('actor_id')->nullable();
            $table->json('meta')->nullable();          // extra (prompt_version, rounds, ...)

            $table->timestamp('created_at')->nullable();

            $table->index(['feature', 'created_at']);
            $table->index(['school_id', 'created_at']);
            $table->index(['course_id', 'created_at']);
            $table->index('model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
