<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherAssignmentSchoolReview;
use App\Models\TeacherDuplicateReview;
use App\Models\TeacherImportBatch;
use App\Services\TeacherSchoolReconciliationService;
use App\Services\TeacherIdentifierVerificationService;
use App\Services\TeacherImportReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TeacherImportReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_review_reports_unresolved_and_collision_without_teacher_identifiers(): void
    {
        $batch = $this->batch();
        $dataset = Dataset::factory()->active()->create();
        School::factory()->for($dataset)->create(['npsn' => '60725746', 'name' => 'Sekolah Collision Satu', 'education_level' => 'SD', 'district' => 'Babo']);
        School::factory()->for($dataset)->create(['npsn' => '60725746', 'name' => 'Sekolah Collision Dua', 'education_level' => 'SMP', 'district' => 'Manimeri']);
        $this->assignment($batch, ['source_npsn' => '60401946', 'school_resolution_status' => 'unresolved', 'nik' => '9876543210000000']);
        $this->assignment($batch, ['source_npsn' => '60725746', 'school_resolution_status' => 'ambiguous']);

        Artisan::call('tifa:review-teacher-schools');
        $output = Artisan::output();
        $this->assertStringContainsString('60401946', $output);
        $this->assertStringContainsString('60725746', $output);
        $this->assertStringContainsString('Sekolah Collision Dua', $output);
        $this->assertStringNotContainsString('9876543210000000', $output);
    }

    public function test_duplicate_review_output_masks_sensitive_identifiers_and_review_state_is_recorded(): void
    {
        $batch = $this->batch();
        $fingerprint = str_repeat('a', 64);
        $this->assignment($batch, ['deduplication_fingerprint' => $fingerprint, 'is_duplicate_candidate' => true, 'full_name' => 'Nama Privat', 'nik' => '9876543210000000', 'nip' => '198001012000011001', 'nuptk' => '1234567890123456', 'phone' => '081234567890']);
        $this->assignment($batch, ['deduplication_fingerprint' => $fingerprint, 'is_duplicate_candidate' => true, 'full_name' => 'Nama Privat', 'nik' => '9876543210000000']);

        Artisan::call('tifa:review-teacher-duplicates');
        $output = Artisan::output();
        $this->assertStringContainsString(substr($fingerprint, 0, 12), $output);
        $this->assertStringNotContainsString('Nama Privat', $output);
        $this->assertStringNotContainsString('9876543210000000', $output);
        $this->assertStringNotContainsString('198001012000011001', $output);
        $this->assertStringNotContainsString('1234567890123456', $output);
        $this->assertStringNotContainsString('081234567890', $output);

        $this->artisan('tifa:record-teacher-duplicate-review '.substr($fingerprint, 0, 12).' exact_duplicate --note="source rows confirmed"')->assertSuccessful();
        $this->assertDatabaseHas('teacher_duplicate_reviews', ['teacher_import_batch_id' => $batch->id, 'deduplication_fingerprint' => $fingerprint, 'review_status' => 'reviewed', 'resolution_type' => 'exact_duplicate']);
        $this->assertNotNull(TeacherDuplicateReview::first()->reviewed_at);
    }

    public function test_authoritative_gate_blocks_school_issues_and_pending_review_then_can_be_ready_without_auto_approval(): void
    {
        $blocked = $this->batch();
        $this->assignment($blocked, ['source_npsn' => '60401946', 'school_resolution_status' => 'unresolved']);
        $this->artisan('tifa:teacher-import-status --batch='.$blocked->id)->assertExitCode(1);

        $ready = $this->batch();
        $fingerprint = str_repeat('b', 64);
        $this->assignment($ready, ['deduplication_fingerprint' => $fingerprint, 'is_duplicate_candidate' => true, 'school_resolution_status' => 'resolved']);
        $this->assignment($ready, ['deduplication_fingerprint' => $fingerprint, 'is_duplicate_candidate' => true, 'school_resolution_status' => 'resolved']);
        TeacherDuplicateReview::create(['teacher_import_batch_id' => $ready->id, 'deduplication_fingerprint' => $fingerprint, 'review_status' => 'reviewed', 'resolution_type' => 'distinct_persons', 'reviewed_at' => now()]);

        $this->artisan('tifa:teacher-import-status --batch='.$ready->id)->expectsOutputToContain('READY FOR AUTHORITATIVE IMPORT')->assertSuccessful();
        $this->assertFalse($ready->fresh()->is_authoritative);
    }

    public function test_school_recommendations_use_exact_name_and_district_but_fuzzy_never_auto_links(): void
    {
        $batch = $this->batch();
        $dataset = Dataset::factory()->active()->create();
        $exactSchool = School::factory()->for($dataset)->create(['name' => 'SMP NEGERI TAROI', 'npsn' => '70000001', 'education_level' => 'SMP', 'district' => 'Tomu']);
        School::factory()->for($dataset)->create(['name' => 'SMP NEGERI TAROI', 'npsn' => '70000002', 'education_level' => 'SMP', 'district' => 'Babo']);
        $exact = $this->assignment($batch, ['source_npsn' => null, 'source_sheet' => 'SMP', 'district' => 'Tomu', 'source_payload' => ['tempat_tugas' => 'SMPN TAROI']]);
        $fuzzy = $this->assignment($batch, ['source_npsn' => null, 'source_sheet' => 'SMP', 'district' => 'Tomu', 'source_payload' => ['tempat_tugas' => 'SMP TARO']]);
        $service = app(TeacherSchoolReconciliationService::class);

        $exactResult = $service->recommendation($exact);
        $this->assertSame('exact_match', $exactResult['classification']);
        $this->assertSame($exactSchool->id, $exactResult['candidates'][0]['id']);
        $fuzzyResult = $service->recommendation($fuzzy);
        $this->assertSame('no_candidate', $fuzzyResult['classification']);
        $this->assertNull($fuzzy->fresh()->school_id);
    }

    public function test_604_and_607_recommendations_and_explicit_resolution_have_audit_trail(): void
    {
        $batch = $this->batch();
        $dataset = Dataset::factory()->active()->create();
        $tuhiba = School::factory()->for($dataset)->create(['name' => 'SMP NEGERI 1 TUHIBA', 'npsn' => '60725746', 'education_level' => 'SMP', 'district' => 'Tuhiba']);
        $taroi = School::factory()->for($dataset)->create(['name' => 'SMPN TAROI', 'npsn' => '60725746', 'education_level' => 'SMP', 'district' => 'Tomu']);
        $for604 = $this->assignment($batch, ['source_npsn' => '60401946', 'source_sheet' => 'SMP', 'district' => 'Tuhiba', 'source_payload' => ['tempat_tugas' => 'SMP NEGERI 1 TUHIBA']]);
        $for607 = $this->assignment($batch, ['source_npsn' => '60725746', 'source_sheet' => 'SMP', 'district' => 'Tomu', 'source_payload' => ['tempat_tugas' => 'SMPN TAROI'], 'school_resolution_status' => 'ambiguous']);
        $service = app(TeacherSchoolReconciliationService::class);

        $this->assertSame($tuhiba->id, $service->recommendation($for604)['candidates'][0]['id']);
        $this->assertSame($taroi->id, $service->recommendation($for607)['candidates'][0]['id']);
        $service->recordResolution($for607, 'link_existing_school', $taroi->id, 'Master collision reviewed.');
        $service->recordResolution($for607->fresh(), 'needs_master_school_correction', null, 'NPSN collision still needs correction.');
        $this->assertSame(2, TeacherAssignmentSchoolReview::where('teacher_assignment_id', $for607->id)->count());
        $this->assertSame('needs_master_school_correction', $for607->fresh()->school_resolution_status);
        $this->artisan('tifa:teacher-import-status --batch='.$batch->id)->assertExitCode(1);
    }

    public function test_identifier_verification_returns_statuses_without_exposing_values_and_gate_remains_blocked(): void
    {
        $batch = $this->batch();
        $fingerprint = str_repeat('c', 64);
        $this->assignment($batch, ['deduplication_fingerprint' => $fingerprint, 'is_duplicate_candidate' => true, 'source_npsn' => '60000001', 'nik' => '1111111111111111', 'nip' => '198001012000011001', 'nuptk' => '2222222222222222', 'full_name' => 'Privat Satu', 'birth_place' => 'Kota', 'birth_date' => '1980-01-01']);
        $this->assignment($batch, ['deduplication_fingerprint' => $fingerprint, 'is_duplicate_candidate' => true, 'source_npsn' => '60000002', 'nik' => '1111111111111111', 'nip' => '198001012000011001', 'nuptk' => '2222222222222222', 'full_name' => 'Privat Satu', 'birth_place' => 'Kota', 'birth_date' => '1980-01-01']);
        $result = app(TeacherIdentifierVerificationService::class)->verify($batch, app(TeacherImportReviewService::class))->first();
        $this->assertSame('match', $result['identifier_statuses']['NIK_MATCH']);
        $this->assertSame('match', $result['identifier_statuses']['NIP_MATCH']);
        $this->assertSame('same_person_multiple_assignments', $result['final_recommendation']);
        Artisan::call('tifa:verify-teacher-review');
        $this->assertStringNotContainsString('1111111111111111', Artisan::output());
        $this->assertStringNotContainsString('Privat Satu', Artisan::output());
        $this->artisan('tifa:teacher-import-status --batch='.$batch->id)->assertExitCode(1);
    }

    public function test_master_npsn_collision_is_warning_and_does_not_change_master_school(): void
    {
        $batch = $this->batch();
        $dataset = Dataset::factory()->active()->create();
        $first = School::factory()->for($dataset)->create(['npsn' => '60725746', 'name' => 'SMPN TAROI', 'education_level' => 'SMP', 'district' => 'Tomu']);
        $second = School::factory()->for($dataset)->create(['npsn' => '60725746', 'name' => 'SMP NEGERI 1 TUHIBA', 'education_level' => 'SMP', 'district' => 'Tuhiba']);
        $assignment = $this->assignment($batch, ['source_npsn' => '60725746', 'school_resolution_status' => 'ambiguous']);
        app(TeacherSchoolReconciliationService::class)->recordResolution($assignment, 'link_existing_school', $first->id, 'Reviewed link.');
        $this->assertSame('60725746', $first->fresh()->npsn);
        $this->assertSame('60725746', $second->fresh()->npsn);
        $this->artisan('tifa:teacher-import-status --batch='.$batch->id)->assertSuccessful();
    }

    public function test_accepted_incomplete_source_retains_nullable_values_without_fabrication(): void
    {
        $batch = $this->batch();
        $assignment = $this->assignment($batch, ['source_npsn' => null, 'district' => null, 'source_payload' => ['tempat_tugas' => null]]);
        app(TeacherSchoolReconciliationService::class)->recordResolution($assignment, 'accepted_incomplete_source', null, 'Final decision retains incomplete source.');
        $stored = $assignment->fresh();
        $this->assertSame('accepted_incomplete_source', $stored->school_resolution_status);
        $this->assertNull($stored->school_id);
        $this->assertNull($stored->source_npsn);
        $this->assertNull($stored->district);
        $this->assertNull($stored->source_payload['tempat_tugas']);
    }

    private function batch(): TeacherImportBatch
    {
        return TeacherImportBatch::create(['source_filename' => 'test.xlsx', 'source_checksum' => hash('sha256', uniqid('', true)), 'reference_period' => 'Maret 2026']);
    }

    /** @param array<string, mixed> $overrides */
    private function assignment(TeacherImportBatch $batch, array $overrides = []): TeacherAssignment
    {
        return TeacherAssignment::create(array_merge([
            'teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(2, 999999),
            'source_npsn' => '60000001', 'school_resolution_status' => 'resolved', 'source_fingerprint' => hash('sha256', uniqid('', true)),
            'deduplication_fingerprint' => null, 'is_duplicate_candidate' => false, 'ptk_type' => 'Guru Kelas',
            'ptk_position' => 'Guru', 'employment_status' => 'PNS', 'education' => 'S1', 'district' => 'Babo',
        ], $overrides));
    }
}
