<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job falliti di tutti gli enti (DB CENTRAL). Il payload contiene tenant_id,
 * messo da QueueTenancyBootstrapper. Se central e tenant coincidono (test,
 * installazione mono-DB) la tabella esiste già.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return 'central';
    }

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('failed_jobs')) {
            return;
        }

        Schema::connection('central')->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        // Non si droppa: nel mono-DB è la tabella dei job falliti del tenant.
    }
};
