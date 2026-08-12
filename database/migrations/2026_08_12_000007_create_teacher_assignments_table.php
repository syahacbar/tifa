<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_sheet', 100);
            $table->unsignedInteger('source_row');
            $table->string('source_npsn', 20)->nullable()->index();
            $table->string('school_resolution_status', 20)->default('unresolved')->index();
            $table->string('source_fingerprint', 64);
            $table->string('deduplication_fingerprint', 64)->nullable()->index();
            $table->boolean('is_duplicate_candidate')->default(false)->index();
            $table->string('full_name')->nullable();
            $table->text('nik')->nullable();
            $table->text('nip')->nullable();
            $table->text('nuptk')->nullable();
            $table->text('phone')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('employment_status_source')->nullable();
            $table->string('employment_status')->nullable()->index();
            $table->string('ptk_type_source')->nullable();
            $table->string('ptk_type')->nullable();
            $table->string('ptk_position_source')->nullable();
            $table->string('ptk_position')->nullable();
            $table->string('education_source')->nullable();
            $table->string('education')->nullable();
            $table->string('district_source')->nullable();
            $table->string('district')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();
            $table->unique(['teacher_import_batch_id', 'source_fingerprint'], 'teacher_assignment_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
