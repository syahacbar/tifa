<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->char('npsn', 8);
            $table->string('name', 200);
            $table->string('education_level', 30);
            $table->string('district', 100);
            $table->string('status', 20);
            $table->unsignedInteger('students_male')->default(0);
            $table->unsignedInteger('students_female')->default(0);
            $table->unsignedInteger('students_total')->default(0);
            $table->unsignedInteger('study_groups')->default(0);
            $table->unsignedInteger('teachers')->default(0);
            $table->unsignedInteger('education_staff')->default(0);
            $table->unsignedInteger('classrooms')->default(0);
            $table->unsignedInteger('laboratories')->default(0);
            $table->unsignedInteger('libraries')->default(0);
            $table->timestamps();

            $table->unique(['dataset_id', 'npsn'], 'schools_dataset_npsn_unique');
            $table->index(['dataset_id', 'education_level'], 'schools_dataset_level_index');
            $table->index(['dataset_id', 'district'], 'schools_dataset_district_index');
            $table->index(['dataset_id', 'status'], 'schools_dataset_status_index');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE schools ADD CONSTRAINT schools_npsn_format_check CHECK (npsn REGEXP '^[0-9]{8}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
