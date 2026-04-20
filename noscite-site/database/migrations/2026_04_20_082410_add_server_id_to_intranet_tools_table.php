<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_tools', function (Blueprint $table) {
            if (!Schema::hasColumn('intranet_tools', 'server_id')) {
                $table->foreignId('server_id')
                    ->nullable()
                    ->after('sort_order')
                    ->constrained('intranet_servers')
                    ->nullOnDelete();
                $table->index('server_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intranet_tools', function (Blueprint $table) {
            if (Schema::hasColumn('intranet_tools', 'server_id')) {
                $table->dropForeign(['server_id']);
                $table->dropIndex(['server_id']);
                $table->dropColumn('server_id');
            }
        });
    }
};
