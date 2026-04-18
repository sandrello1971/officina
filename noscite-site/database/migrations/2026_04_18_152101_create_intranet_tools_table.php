<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_tools', function (Blueprint $table) {
            $table->id();
            $table->string('section');
            $table->string('type')->default('tool');
            $table->string('icon')->default('🔧');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url');
            $table->string('label')->nullable();
            $table->string('credentials')->nullable();
            $table->string('status')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_tools');
    }
};
