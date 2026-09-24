<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Motore generazione corsi da KB — bozza AI del manuale discente. Separata da
// `content` (il contenuto pubblicato, invariato per i moduli esistenti): viene
// promossa a `content` solo quando il formatore approva e pubblica il modulo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->text('content_draft')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('content_draft');
        });
    }
};
