<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Con quale chiave è stata fatta la chiamata: `platform` (nostra, da fatturare
 * all'ente e soggetta al budget) o `tenant` (chiave propria dell'ente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->string('key_source', 16)->default('platform')->after('status');
            $table->index(['key_source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table) {
            $table->dropIndex(['key_source', 'created_at']);
            $table->dropColumn('key_source');
        });
    }
};
