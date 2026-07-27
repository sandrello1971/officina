<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// News AI — singola notizia recuperata dalla ricerca online.
//
// HITL: nasce 'draft'; un admin la porta a 'published' (visibile ai discenti) o 'rejected'.
// Le fonti (url + nome + data) sono riportate dal modello nel suo JSON (non parsate dai
// metadata web_search). `tags` classifica la news per argomento (generati dall'AI).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('run_id')->nullable()->constrained('news_runs')->nullOnDelete();

            $table->string('title');
            $table->text('summary');
            $table->text('body')->nullable();

            // Fonte (riportata dal modello): url + nome leggibile + data della notizia.
            $table->text('source_url')->nullable();
            $table->string('source_name')->nullable();
            $table->date('source_published_at')->nullable();

            $table->jsonb('tags')->nullable();           // array di stringhe brevi (argomenti)
            $table->decimal('confidence', 4, 3)->nullable(); // 0.000–1.000

            // Ciclo HITL.
            $table->string('status', 12)->default('draft'); // draft | published | rejected
            $table->foreignUuid('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['status', 'published_at']);
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE news_items ADD CONSTRAINT news_items_status_check
            CHECK (status IN ('draft', 'published', 'rejected'))");
        DB::statement("ALTER TABLE news_items ADD CONSTRAINT news_items_confidence_check
            CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1))");
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
