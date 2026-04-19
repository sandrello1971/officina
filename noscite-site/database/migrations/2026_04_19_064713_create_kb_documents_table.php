<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_documents', function (Blueprint $table) {
            $table->id();
            $table->string('file_stem')->unique();
            $table->string('title')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('lingua')->default('it');
            $table->text('sommario')->nullable();
            $table->json('tags')->nullable();
            $table->json('argomenti')->nullable();
            $table->string('file_originale')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->date('data_catalogazione')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_documents');
    }
};
