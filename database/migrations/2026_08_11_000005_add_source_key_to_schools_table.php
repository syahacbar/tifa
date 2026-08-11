<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('source_key', 64)->nullable()->after('npsn');
            $table->dropUnique('schools_dataset_npsn_unique');
            $table->index(['dataset_id', 'npsn'], 'schools_dataset_npsn_index');
        });

        DB::table('schools')
            ->select(['id', 'education_level', 'name', 'district'])
            ->orderBy('id')
            ->get()
            ->each(function (object $school): void {
                $parts = array_map(function (?string $value): string {
                    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value ?? '') ?? ''));
                }, [$school->education_level, $school->name, $school->district]);

                DB::table('schools')
                    ->where('id', $school->id)
                    ->update(['source_key' => hash('sha256', implode('|', $parts))]);
            });

        Schema::table('schools', function (Blueprint $table) {
            $table->string('source_key', 64)->nullable(false)->change();
            $table->unique(['dataset_id', 'source_key'], 'schools_dataset_source_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropUnique('schools_dataset_source_key_unique');
            $table->dropIndex('schools_dataset_npsn_index');
            $table->unique(['dataset_id', 'npsn'], 'schools_dataset_npsn_unique');
            $table->dropColumn('source_key');
        });
    }
};
