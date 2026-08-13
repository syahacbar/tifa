<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TifaQuickQuestionCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ten_additional_demo_quick_questions_are_deterministic_and_have_expected_presentations(): void
    {
        $dataset = Dataset::factory()->active()->create();
        $schools = collect([
            ['SD Babo', 'SD', 'Babo', 'Negeri'], ['SMP Babo', 'SMP', 'Babo', 'Swasta'],
            ['SD Tuhiba', 'SD', 'Tuhiba', 'Negeri'], ['SMP Sumuri', 'SMP', 'Sumuri', 'Swasta'],
            ['SD Bintuni', 'SD', 'Bintuni', 'Negeri'], ['SD Manimeri', 'SD', 'Manimeri', 'Swasta'], ['SD Tomu', 'SD', 'Tomu', 'Negeri'],
        ])->map(fn (array $school) => School::factory()->for($dataset)->create(['name' => $school[0], 'education_level' => $school[1], 'district' => $school[2], 'status' => $school[3], 'students_total' => 100]));
        $batch = TeacherImportBatch::create(['source_filename' => 'quick-catalog.xlsx', 'source_checksum' => hash('sha256', 'quick-catalog'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach ([['Bintuni', 'PNS', $schools[4], 5], ['Manimeri', 'PPPK', $schools[5], 4], ['Sumuri', 'PNS', $schools[3], 3], ['Babo', 'PPPK', $schools[0], 2], ['Tomu', 'PNS', $schools[6], 1], ['Tuhiba', 'PNS', $schools[2], 1]] as [$district, $status, $school, $total]) {
            for ($index = 0; $index < $total; $index++) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => $school->education_level, 'source_row' => random_int(1, 999999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => hash('sha256', $district.$status.$school->id.$index), 'school_id' => $school->id, 'school_resolution_status' => 'resolved', 'district' => $district, 'employment_status' => $status]);
        }
        Http::fake(['*' => Http::response(['error' => 'offline'], 503)]);

        foreach ([
            ['Berapa jumlah sekolah negeri dan swasta di Kabupaten Teluk Bintuni?', 'status_breakdown', 'bar_chart', null],
            ['Berapa jumlah siswa di Kabupaten Teluk Bintuni?', 'student_total', 'kpi', null],
            ['Tampilkan sekolah yang ada di Distrik Babo.', 'school_list', 'table', null],
            ['Berapa jumlah guru PNS yang mengajar di tingkat SMP?', 'teacher_analytics', 'kpi', null],
            ['Tampilkan SD di Distrik Tuhiba.', 'school_list', 'table', null],
            ['Tampilkan 5 SD dengan jumlah guru terbanyak.', 'teacher_analytics', 'bar_chart', 5],
            ['Tampilkan SMP di Distrik Sumuri.', 'school_list', 'table', null],
            ['Berapa jumlah guru di Distrik Bintuni?', 'teacher_analytics', 'kpi', null],
            ['Sebutkan 5 distrik dengan jumlah guru terbanyak.', 'teacher_analytics', 'bar_chart', 5],
            ['Berapa jumlah guru berdasarkan status kepegawaian?', 'teacher_analytics', 'bar_chart', null],
        ] as [$question, $intent, $presentation, $recordCount]) {
            $response = $this->postJson('/api/tifa/ask', ['question' => $question])->assertOk();
            $this->assertSame($intent, $response->json('intent.type') ?? $response->json('intent.action'), $question);
            $response->assertJsonPath('presentation.type', $presentation);
            if ($recordCount !== null) {
                $this->assertCount($recordCount, $response->json('data.records'), $question);
                $this->assertCount($recordCount, $response->json('presentation.data'), $question);
            }
        }
        Http::assertNothingSent();
    }
}
