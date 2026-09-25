<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operatori della console di piattaforma (DB CENTRAL). Realm separato dagli
 * `admins` dei singoli enti, che vivono nel DB di ciascun tenant.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return 'central';
    }

    public function up(): void
    {
        Schema::connection('central')->create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('platform_users');
    }
};
