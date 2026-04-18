<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('role')->nullable();
            $table->string('website')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_cards');
    }
};
