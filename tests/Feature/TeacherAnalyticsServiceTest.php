<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use App\Services\TeacherAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class TeacherAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_latest_authoritative_batch_and_counts_assignments_and_unique_teachers(): void
    {
        $inactive = $this->batch(false); $this->assignment($inactive, 'ignored');
        $batch = $this->batch(true); $this->assignment($batch, 'same'); $this->assignment($batch, 'same'); $this->assignment($batch, 'other', ['employment_status' => null, 'district' => null]);
        $service = app(TeacherAnalyticsService::class);
        $this->assertSame(3, $service->query(['metric' => 'assignment_count'])['value']);
        $this->assertSame(2, $service->query(['metric' => 'unique_teacher_count'])['value']);
        $this->assertSame($batch->id, $service->query(['metric' => 'assignment_count'])['batch']['id']);
    }

    public function test_filters_grouping_school_boundaries_and_null_bucket_are_safe(): void
    {
        $batch = $this->batch(true); $school = School::factory()->create(['name' => 'SD Contoh', 'education_level' => 'SD']);
        $this->assignment($batch, 'one', ['school_id' => $school->id, 'school_resolution_status' => 'resolved', 'source_sheet' => 'SD', 'district' => 'Bintuni', 'employment_status' => 'PPPK']);
        $this->assignment($batch, 'two', ['school_resolution_status' => 'accepted_unresolved', 'source_sheet' => 'SD', 'district' => null, 'employment_status' => null]);
        $service = app(TeacherAnalyticsService::class);
        $this->assertSame(1, $service->query(['metric' => 'assignment_count', 'filters' => ['education_level' => 'SD', 'employment_status' => 'PPPK']])['value']);
        $districts = $service->query(['metric' => 'assignment_count', 'group_by' => 'district'])['data']['records'];
        $this->assertContains(['label' => 'Tidak tersedia', 'value' => 1], $districts);
        $schools = $service->query(['metric' => 'assignment_count', 'group_by' => 'school'])['data']['records'];
        $this->assertContains(['label' => 'Unresolved source school', 'value' => 1], $schools);
        $this->assertSame(1, $service->query(['metric' => 'assignment_count', 'filters' => ['school_id' => $school->id]])['value']);
    }

    public function test_rejects_invalid_contract_and_never_returns_sensitive_columns(): void
    {
        $batch = $this->batch(true); $this->assignment($batch, 'one', ['nik' => '1234567890123456']);
        $service = app(TeacherAnalyticsService::class);
        $this->expectException(ValidationException::class);
        $service->query(['metric' => 'assignment_count', 'filters' => ['nik' => '123']]);
    }

    public function test_requires_authoritative_batch(): void
    {
        $this->expectException(RuntimeException::class);
        app(TeacherAnalyticsService::class)->query(['metric' => 'assignment_count']);
    }

    private function batch(bool $authoritative): TeacherImportBatch
    {
        return TeacherImportBatch::create(['source_filename' => 'test.xlsx', 'source_checksum' => hash('sha256', uniqid('', true)), 'reference_period' => 'Maret 2026', 'is_authoritative' => $authoritative]);
    }

    /** @param array<string, mixed> $overrides */
    private function assignment(TeacherImportBatch $batch, string $fingerprint, array $overrides = []): TeacherAssignment
    {
        return TeacherAssignment::create(array_merge(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(1, 999999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_resolution_status' => 'resolved', 'district' => 'Bintuni', 'employment_status' => 'PNS'], $overrides));
    }
}
