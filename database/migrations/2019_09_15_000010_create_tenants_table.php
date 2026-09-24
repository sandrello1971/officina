<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabella `tenants` nel DB CENTRAL: un record per ente.
 *
 * Colonne reali = App\Models\Tenant::getCustomColumns(); il resto degli
 * attributi finisce nel JSON `data` (VirtualColumn di stancl/tenancy), incluso
 * `tenancy_db_name` che pinna il DB del tenant zero (atheneum_db).
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active'); // active | suspended
            // Host base B: l'ente è servito su admin.B e learn.B.
            $table->string('base_host')->unique();
            $table->jsonb('licensed_modules')->nullable();
            $table->string('ai_key_mode')->default('platform'); // platform | tenant | both
            $table->decimal('ai_monthly_budget_usd', 10, 2)->nullable(); // null = nessun limite
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenants');
    }
};
