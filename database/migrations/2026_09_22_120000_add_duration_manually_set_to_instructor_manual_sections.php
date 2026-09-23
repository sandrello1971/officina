<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_manual_sections', function (Blueprint $table) {
            $table->boolean('duration_manually_set')->default(false)->after('estimated_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_manual_sections', function (Blueprint $table) {
            $table->dropColumn('duration_manually_set');
        });
    }
};
