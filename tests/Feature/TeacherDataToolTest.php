<?php

namespace Tests\Feature;

use App\Exceptions\TeacherDataToolException;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use App\Services\TeacherDataTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherDataToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_contract_uses_authoritative_batch_and_returns_provenance_and_quality(): void
    {
        $staging = $this->batch(false); $this->assignment($staging, 'staging');
        $batch = $this->batch(true); $this->assignment($batch, 'one'); $this->assignment($batch, 'two', ['school_resolution_status' => 'accepted_unresolved']);
        $result = app(TeacherDataTool::class)->execute($this->contract());
        $this->assertSame(2, $result['data']['value']); $this->assertSame($batch->id, $result['provenance']['batch_id']);
        $this->assertTrue($result['provenance']['authoritative']); $this->assertSame(1, $result['quality']['school_resolution']['accepted_unresolved']);
    }

    public function test_breakdown_ranking_and_school_quality_are_bounded(): void
    {
        $batch = $this->batch(true); $this->assignment($batch, 'one', ['district' => 'Bintuni']); $this->assignment($batch, 'two', ['school_resolution_status' => 'accepted_unresolved']);
        $breakdown = app(TeacherDataTool::class)->execute($this->contract(['operation' => 'breakdown', 'group_by' => 'district']));
        $this->assertSame('ok', $breakdown['status']);
        $ranking = app(TeacherDataTool::class)->execute($this->contract(['operation' => 'ranking', 'group_by' => 'school', 'top_n' => 1]));
        $this->assertFalse($ranking['quality']['complete_for_requested_dimension']);
    }

    #[DataProvider('invalidContracts')]
    public function test_invalid_or_private_contracts_are_rejected(array $changes): void
    {
        $this->batch(true);
        try { app(TeacherDataTool::class)->execute($this->contract($changes)); $this->fail('Expected tool exception.'); }
        catch (TeacherDataToolException $exception) { $this->assertContains($exception->errorCode, ['invalid_operation','invalid_metric','invalid_filter','invalid_group_by','invalid_combination']); }
    }

    public static function invalidContracts(): array
    {
        return [[['operation' => 'sql']], [['metric' => 'count(*)']], [['filters' => ['nik' => '123']]], [['group_by' => 'nik']], [['top_n' => 21]], [['operation' => 'ranking', 'group_by' => null, 'top_n' => 1]]];
    }

    private function contract(array $changes = []): array
    {
        return array_replace_recursive(['version' => 'v1', 'operation' => 'count', 'entity' => 'teacher_identity', 'metric' => 'unique_teacher_count', 'filters' => ['education_level' => null, 'district' => null, 'school_id' => null, 'employment_status' => null, 'ptk_type' => null, 'ptk_position' => null, 'education' => null], 'group_by' => null, 'top_n' => null], $changes);
    }

    private function batch(bool $authoritative): TeacherImportBatch { return TeacherImportBatch::create(['source_filename' => uniqid().'.xlsx', 'source_checksum' => hash('sha256', uniqid('', true)), 'reference_period' => 'Maret 2026', 'is_authoritative' => $authoritative]); }
    private function assignment(TeacherImportBatch $batch, string $fingerprint, array $overrides = []): void { TeacherAssignment::create(array_merge(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(1, 99999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => $fingerprint, 'school_resolution_status' => 'resolved', 'district' => 'Bintuni'], $overrides)); }
}
