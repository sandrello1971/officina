<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->date('data_documento')->nullable()->after('data_catalogazione');
            $table->string('organizzazioni')->nullable()->after('lingua');
            $table->string('sentiment')->nullable()->after('organizzazioni');
            $table->string('complessita')->nullable()->after('sentiment');
            $table->json('persone')->nullable()->after('complessita');
            $table->json('luoghi')->nullable()->after('persone');
            $table->json('parole_chiave')->nullable()->after('luoghi');
        });
    }

    public function down(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->dropColumn([
                'data_documento', 'organizzazioni', 'sentiment',
                'complessita', 'persone', 'luoghi', 'parole_chiave',
            ]);
        });
    }
};
