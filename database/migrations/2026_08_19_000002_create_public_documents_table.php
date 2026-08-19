<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('file_path', 500);
            $table->string('thumbnail_path', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->date('published_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_documents');
    }
};
