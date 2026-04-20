<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_tools', function (Blueprint $table) {
            if (!Schema::hasColumn('intranet_tools', 'github_url')) {
                $table->string('github_url', 500)->nullable()->after('url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intranet_tools', function (Blueprint $table) {
            if (Schema::hasColumn('intranet_tools', 'github_url')) {
                $table->dropColumn('github_url');
            }
        });
    }
};
