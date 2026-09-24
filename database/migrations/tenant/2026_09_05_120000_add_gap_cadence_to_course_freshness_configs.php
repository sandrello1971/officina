<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// P26.3 — Cadenza dello Scout di copertura per corso, gemella di `cadence` (Freshness).
// Stessa scelta conservativa sul costo: default 'off' (OPT-IN), l'admin abilita dove serve.
// Riusa la riga di configurazione esistente per corso invece di una tabella dedicata.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->string('gap_cadence', 12)->default('off')->after('topic');
            $table->timestamp('gap_last_run_at')->nullable()->after('gap_cadence');
        });

        DB::statement("ALTER TABLE course_freshness_configs ADD CONSTRAINT course_freshness_configs_gap_cadence_check
            CHECK (gap_cadence IN ('off', 'weekly', 'monthly', 'quarterly'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE course_freshness_configs DROP CONSTRAINT IF EXISTS course_freshness_configs_gap_cadence_check');
        Schema::table('course_freshness_configs', function (Blueprint $table) {
            $table->dropColumn(['gap_cadence', 'gap_last_run_at']);
        });
    }
};
