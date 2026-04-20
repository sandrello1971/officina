<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->text('body_md')->nullable()->after('sommario');
        });
    }

    public function down(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->dropColumn('body_md');
        });
    }
};
