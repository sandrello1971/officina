<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;
    public function up(): void
    {
        Schema::create('documents_rag', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignUuid('module_id')->nullable()->constrained('modules')->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('file_path')->nullable();
            $table->integer('chunk_index')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        try {
            DB::statement('ALTER TABLE documents_rag ADD COLUMN embedding vector(1536)');
            DB::statement('CREATE INDEX documents_rag_embedding_idx ON documents_rag USING ivfflat (embedding vector_cosine_ops) WITH (lists = 10)');
        } catch (\Throwable $e) {
            // pgvector non disponibile: la colonna embedding verra aggiunta manualmente in seguito
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_rag');
    }
};
