<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->unsignedInteger('blogs_count')->default(0);
            $table->timestamps();

            $table->index(['name']);
            $table->index(['slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
