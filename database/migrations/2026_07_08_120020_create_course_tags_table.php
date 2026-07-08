<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tassonomia corsi — tag TRASVERSALI (un corso ha N tag). Tabella gestita,
// tag creati per-deployment via admin, nessun seed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_tags', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_tags');
    }
};
