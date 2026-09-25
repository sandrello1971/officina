<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Appello del formatore: stato della giornata (presente / assente / assente
 * giustificato), orari di entrata e uscita (ritardo e uscita anticipata),
 * nota e chi ha compilato. Gli assenti hanno un record esplicito a 0 ore.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('status', 24)->nullable()->after('source');
            $table->time('arrived_at')->nullable()->after('status');
            $table->time('left_at')->nullable()->after('arrived_at');
            $table->text('note')->nullable()->after('left_at');
            $table->string('marked_by')->nullable()->after('note');
        });

        DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_status_check CHECK (status IS NULL OR status IN ('presente','assente','assente_giustificato'))");
        // Un solo appello per discente e giornata.
        DB::statement("CREATE UNIQUE INDEX attendance_records_session_student_mark_unique ON attendance_records (course_session_id, student_id) WHERE source = 'instructor_mark'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_records_session_student_mark_unique');
        DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_status_check');
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['status', 'arrived_at', 'left_at', 'note', 'marked_by']);
        });
    }
};
