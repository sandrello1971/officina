<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pivot corso↔tag (molti-a-molti). PK composta, cascade su entrambi i lati.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_course_tag', function (Blueprint $table) {
            $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('course_tag_id')->constrained('course_tags')->cascadeOnDelete();

            $table->primary(['course_id', 'course_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_course_tag');
    }
};
