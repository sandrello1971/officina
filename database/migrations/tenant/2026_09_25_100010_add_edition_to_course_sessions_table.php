<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Le giornate appartengono a un'edizione e sono numerate (Giornata 1…N). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_sessions', function (Blueprint $table) {
            $table->foreignUuid('course_edition_id')->nullable()->after('course_id')
                ->constrained('course_editions')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number')->nullable()->after('course_edition_id');
            $table->index(['course_edition_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('course_sessions', function (Blueprint $table) {
            $table->dropIndex(['course_edition_id', 'scheduled_at']);
            $table->dropConstrainedForeignId('course_edition_id');
            $table->dropColumn('day_number');
        });
    }
};
