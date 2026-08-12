<?php

namespace Tests\Feature;

use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use App\Services\TeacherDataNormalizer;
use App\Services\TeacherImportService;
use App\Services\TeacherSnapshotValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizer_maps_placeholders_and_pppk_variants(): void
    {
        $this->assertNull(TeacherDataNormalizer::nullable('-'));
        $this->assertNull(TeacherDataNormalizer::date('1900-01-01'));
        $this->assertSame('PPPK Tahap II', TeacherDataNormalizer::employmentStatus('PPPK TAHAP 2'));
        $this->assertSame('PPPK Tahap II', TeacherDataNormalizer::employmentStatus('PPPK TAHAP II'));
    }

    public function test_dry_run_and_repeat_import_preserve_source_and_are_idempotent(): void
    {
        $path = storage_path('app/imports/nominatif-guru-maret-2026.xlsx');
        if (! is_file($path)) {
            $this->markTestSkipped('Workbook nominatif guru privat tidak tersedia.');
        }

        $modifiedAt = filemtime($path);
        $importer = app(TeacherImportService::class);
        $dryRun = $importer->import($path, true);
        $this->assertSame(1457, $dryRun['records']);
        $this->assertSame(0, TeacherImportBatch::count());
        $this->assertSame(0, TeacherAssignment::count());

        $first = $importer->import($path);
        $assignment = TeacherAssignment::query()->firstOrFail();
        $assignment->update(['school_resolution_status' => 'accepted_unresolved', 'school_id' => null]);
        $second = $importer->import($path);
        $this->assertSame(1, TeacherImportBatch::count());
        $this->assertSame($first['records'], TeacherAssignment::count());
        $this->assertSame(0, $second['created']);
        $this->assertGreaterThan(0, $second['updated']);
        $this->assertSame('accepted_unresolved', $assignment->fresh()->school_resolution_status);
        $validation = app(TeacherSnapshotValidationService::class)->validate(TeacherImportBatch::firstOrFail());
        $this->assertSame(1457, $validation['total_assignments']);
        foreach (['SKB' => 22, 'KB,TK,PAUD' => 109, 'SD' => 643, 'SMP' => 402, 'SMA.' => 281] as $sheet => $total) {
            $this->assertSame($total, $validation['sheet_counts'][$sheet]);
        }
        clearstatcache(true, $path);
        $this->assertSame($modifiedAt, filemtime($path));
    }
}
