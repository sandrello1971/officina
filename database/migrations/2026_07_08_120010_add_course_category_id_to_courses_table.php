<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Additiva: FK categoria su courses. Nullable — un corso può non avere categoria
// (fallback "Altri corsi"). nullOnDelete: cancellando la categoria il corso sopravvive.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignUuid('course_category_id')
                ->nullable()
                ->constrained('course_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_category_id');
        });
    }
};
