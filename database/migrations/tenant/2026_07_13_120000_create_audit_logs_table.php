<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail delle azioni admin/docente (chi ha fatto cosa, quando). Tabella
 * interrogabile (coerente col principio "mai solo log") a fini di accountability
 * e compliance (minori + AI generativa). Popolata dal middleware AuditTrail su
 * ogni richiesta mutante (non-GET) nelle aree /admin e /docente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));

            $table->string('area', 20);            // admin | docente
            $table->string('actor_type', 20);      // admin | student | system
            $table->uuid('actor_id')->nullable();
            $table->string('actor_label')->nullable(); // email/nome snapshot al momento

            $table->string('action');              // route name, o "post /admin/quizzes"
            $table->string('method', 10);
            $table->string('path');
            $table->smallInteger('status')->nullable(); // HTTP status della risposta

            $table->string('subject_type')->nullable(); // nome del primo parametro di rotta (es. 'course')
            $table->string('subject_id')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();       // parametri di rotta (id), MAI il body (no PII/segreti)

            $table->timestamp('created_at')->nullable();

            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['area', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
