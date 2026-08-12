<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_duplicate_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('deduplication_fingerprint', 64);
            $table->string('review_status', 20)->default('pending')->index();
            $table->string('resolution_type', 40)->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['teacher_import_batch_id', 'deduplication_fingerprint'], 'teacher_duplicate_review_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_duplicate_reviews');
    }
};
