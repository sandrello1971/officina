<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('microsoft_id')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('avatar')->nullable();
            $table->string('token');
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_sessions');
    }
};
