<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_manual_sections', function (Blueprint $table) {
            $table->unsignedInteger('estimated_minutes')->nullable()->after('content_html');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_manual_sections', function (Blueprint $table) {
            $table->dropColumn('estimated_minutes');
        });
    }
};
