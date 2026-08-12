<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use App\Exceptions\TeacherDataToolException;
use App\Services\TifaAssistantService;
use App\Services\TeacherDataTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherSemanticExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_rankings_execute_ascending_and_descending_with_deterministic_ties(): void
    {
        $batch = $this->batch();
        $this->schoolWithTeachers($batch, 'SMA CHARLIE', 2, 'SMA');
        $this->schoolWithTeachers($batch, 'SMA ALFA', 2, 'SMA');
        $this->schoolWithTeachers($batch, 'SMA BETA', 4, 'SMA');
        $this->schoolWithTeachers($batch, 'SMA DELTA', 1, 'SMA');
        Http::fake();

        $assistant = app(TifaAssistantService::class);
        $least = $assistant->ask('Sekolah mana yang gurunya paling sedikit?');
        $this->assertSame('asc', $least['intent']['sort']['direction']);
        $this->assertSame('SMA DELTA', $least['data']['records'][0]['label']);
        $this->assertStringContainsString('paling sedikit', $least['answer']);

        $fiveLeast = $assistant->ask('5 sekolah dengan guru paling sedikit');
        $this->assertSame(['SMA DELTA', 'SMA ALFA', 'SMA CHARLIE', 'SMA BETA'], array_column($fiveLeast['data']['records'], 'label'));
        $this->assertStringContainsString('paling sedikit', $fiveLeast['answer']);

        $most = $assistant->ask('Sekolah mana guru terbanyak?');
        $this->assertSame('desc', $most['intent']['sort']['direction']);
        $this->assertSame('SMA BETA', $most['data']['records'][0]['label']);
        Http::assertNothingSent();
    }

    public function test_district_rankings_support_ascending_with_level_filter(): void
    {
        $batch = $this->batch();
        $this->districtTeachers($batch, 'Bintuni', 4, 'SD');
        $this->districtTeachers($batch, 'Manimeri', 1, 'SD');
        $this->districtTeachers($batch, 'Babo', 1, 'SD');
        Http::fake();

        $result = app(TifaAssistantService::class)->ask('3 distrik dengan guru SD paling sedikit');
        $this->assertSame('asc', $result['intent']['sort']['direction']);
        $this->assertSame(['Babo', 'Manimeri', 'Bintuni'], array_column($result['data']['records'], 'label'));
        Http::assertNothingSent();
    }

    public function test_comparisons_are_scoped_to_requested_districts_and_preserve_question_order(): void
    {
        $batch = $this->batch();
        $this->districtTeachers($batch, 'Bintuni', 3, 'SD');
        $this->districtTeachers($batch, 'Manimeri', 2, 'SD');
        $this->districtTeachers($batch, 'Babo', 5, 'SD');
        Http::fake();

        foreach ([
            'Bandingkan jumlah guru Bintuni dan Manimeri',
            'Bandingkan guru Bintuni dengan Manimeri',
            'Perbandingan jumlah guru Bintuni dan Manimeri',
            'Bandingkan jumlah guru distrik Bintuni dan distrik Manimeri',
            'Bandingkan jumlah guru SD Bintuni dan Manimeri',
        ] as $question) {
            $result = app(TifaAssistantService::class)->ask($question);
            $this->assertSame('comparison', $result['intent']['operation'], $question);
            $this->assertSame('district', $result['intent']['group_by'], $question);
            $this->assertSame(['Bintuni', 'Manimeri'], $result['intent']['comparison_values'], $question);
            $this->assertSame(['Bintuni', 'Manimeri'], array_column($result['data']['records'], 'label'), $question);
            $this->assertSame([3, 2], array_column($result['data']['records'], 'value'), $question);
        }
        Http::assertNothingSent();
    }

    public function test_v1_tool_defaults_missing_ranking_sort_to_desc_and_validates_comparison(): void
    {
        $batch = $this->batch();
        $this->districtTeachers($batch, 'Bintuni', 3);
        $this->districtTeachers($batch, 'Manimeri', 1);
        $tool = app(TeacherDataTool::class);
        $base = ['version' => 'v1', 'entity' => 'teacher_identity', 'metric' => 'unique_teacher_count', 'filters' => ['education_level' => null, 'district' => null, 'school_id' => null, 'employment_status' => null, 'ptk_type' => null, 'ptk_position' => null, 'education' => null], 'group_by' => 'district'];

        $ranking = $tool->execute([...$base, 'operation' => 'ranking', 'top_n' => 1]);
        $this->assertSame('desc', $ranking['context']['sort']['direction']);
        $this->assertSame('Bintuni', $ranking['data']['records'][0]['label']);

        $comparison = $tool->execute([...$base, 'operation' => 'comparison', 'top_n' => null, 'comparison_values' => ['Manimeri', 'Bintuni']]);
        $this->assertSame(['Manimeri', 'Bintuni'], array_column($comparison['data']['records'], 'label'));
    }

    public function test_v1_tool_rejects_invalid_sort_and_invalid_comparison_values(): void
    {
        $this->batch();
        $tool = app(TeacherDataTool::class);
        $base = ['version' => 'v1', 'entity' => 'teacher_identity', 'metric' => 'unique_teacher_count', 'filters' => ['education_level' => null, 'district' => null, 'school_id' => null, 'employment_status' => null, 'ptk_type' => null, 'ptk_position' => null, 'education' => null], 'group_by' => 'district', 'top_n' => 1];

        try {
            $tool->execute([...$base, 'operation' => 'ranking', 'sort' => ['field' => 'value', 'direction' => 'invalid']]);
            $this->fail('Invalid sort should be rejected.');
        } catch (TeacherDataToolException $exception) {
            $this->assertSame('invalid_operation', $exception->errorCode);
        }

        try {
            $tool->execute([...$base, 'operation' => 'comparison', 'top_n' => null, 'comparison_values' => ['Bintuni']]);
            $this->fail('Single comparison value should be rejected.');
        } catch (TeacherDataToolException $exception) {
            $this->assertSame('invalid_operation', $exception->errorCode);
        }
    }

    public function test_comparison_with_an_unresolved_district_is_rejected_instead_of_running_a_partial_or_total_query(): void
    {
        $batch = $this->batch();
        $this->districtTeachers($batch, 'Bintuni', 3);
        $this->postJson('/api/tifa/ask', ['question' => 'Bandingkan jumlah guru Bintuni dan Distrik Tidak Ada'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Comparison membutuhkan dua distrik yang dapat dikenali.');
    }

    private function batch(): TeacherImportBatch
    {
        return TeacherImportBatch::create(['source_filename' => uniqid().'.xlsx', 'source_checksum' => hash('sha256', uniqid('', true)), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
    }

    private function schoolWithTeachers(TeacherImportBatch $batch, string $name, int $total, string $level): void
    {
        $school = School::factory()->create(['name' => $name, 'education_level' => $level]);
        for ($index = 0; $index < $total; $index++) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => $level === 'SMA' ? 'SMA.' : $level, 'source_row' => random_int(10000, 99999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => hash('sha256', $name.$index), 'school_id' => $school->id, 'school_resolution_status' => 'resolved']);
    }

    private function districtTeachers(TeacherImportBatch $batch, string $district, int $total, string $level = 'SD'): void
    {
        for ($index = 0; $index < $total; $index++) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => $level === 'SMA' ? 'SMA.' : $level, 'source_row' => random_int(100000, 999999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => hash('sha256', $district.$level.$index.uniqid()), 'school_resolution_status' => 'resolved', 'district' => $district]);
    }
}
