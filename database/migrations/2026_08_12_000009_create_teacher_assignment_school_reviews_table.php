<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignment_school_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('resolution_type', 40)->index();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reviewer_note')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->index(['teacher_assignment_id', 'is_current'], 'teacher_assignment_current_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignment_school_reviews');
    }
};
