<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectTeachersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_inspects_teacher_workbook_without_writing_database_or_workbook(): void
    {
        $path = storage_path('app/imports/nominatif-guru-maret-2026.xlsx');
        if (! is_file($path)) {
            $this->markTestSkipped('Workbook nominatif guru privat tidak tersedia.');
        }

        $modifiedAt = filemtime($path);
        $datasetCount = Dataset::count();
        $schoolCount = School::count();

        $this->artisan('tifa:inspect-teachers')
            ->expectsOutputToContain('Inspeksi workbook nominatif guru (read-only')
            ->expectsOutputToContain('NPSN tidak cocok dengan tabel schools')
            ->expectsOutputToContain('Nilai null/placeholder')
            ->assertSuccessful();

        clearstatcache(true, $path);
        $this->assertSame($modifiedAt, filemtime($path));
        $this->assertSame($datasetCount, Dataset::count());
        $this->assertSame($schoolCount, School::count());
    }
}
