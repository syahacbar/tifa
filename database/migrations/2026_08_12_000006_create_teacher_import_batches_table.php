<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_filename');
            $table->string('source_checksum', 64)->unique();
            $table->string('reference_period')->nullable()->index();
            $table->string('status', 30)->default('imported');
            $table->boolean('is_authoritative')->default(false);
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('unresolved_count')->default(0);
            $table->unsignedInteger('duplicate_candidate_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_import_batches');
    }
};
