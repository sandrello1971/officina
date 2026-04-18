<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('excerpt', 500)->nullable();
            $table->string('cover_image_url')->nullable();
            $table->json('tags')->nullable();
            $table->string('category')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('author_name')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('views_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->timestamps();

            $table->index(['published', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
