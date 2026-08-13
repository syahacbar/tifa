<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TifaDeterministicCapabilityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_ten_requested_school_and_teacher_questions_are_deterministic_and_structured(): void
    {
        $dataset = Dataset::factory()->active()->create();
        $schools = collect([
            ['SMP NEGERI BINTUNI', 'SMP', 'Bintuni', 'Negeri', 8],
            ['SMP SWASTA BINTUNI', 'SMP', 'Bintuni', 'Swasta', 7],
            ['SMP MANIMERI', 'SMP', 'Manimeri', 'Negeri', 6],
            ['SMP SUMURI', 'SMP', 'Sumuri', 'Negeri', 5],
            ['SMP TOMU', 'SMP', 'Tomu', 'Swasta', 4],
            ['SMA BINTUNI', 'SMA', 'Bintuni', 'Negeri', 5],
            ['SMA MANIMERI', 'SMA', 'Manimeri', 'Swasta', 4],
            ['SD MANIMERI', 'SD', 'Manimeri', 'Negeri', 3],
            ['SD BABO', 'SD', 'Babo', 'Negeri', 2],
        ])->map(fn (array $row) => [
            School::factory()->for($dataset)->create(['name' => $row[0], 'education_level' => $row[1], 'district' => $row[2], 'status' => $row[3]]),
            $row[4],
        ]);
        $batch = TeacherImportBatch::create(['source_filename' => 'capability-audit.xlsx', 'source_checksum' => hash('sha256', 'capability-audit'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        $sourceRow = 1;
        foreach ($schools as [$school, $total]) {
            for ($index = 0; $index < $total; $index++) {
                TeacherAssignment::create([
                    'teacher_import_batch_id' => $batch->id,
                    'source_sheet' => $school->education_level === 'SMA' ? 'SMA.' : $school->education_level,
                    'source_row' => $sourceRow++,
                    'source_fingerprint' => hash('sha256', $school->id.'-source-'.$index),
                    'deduplication_fingerprint' => hash('sha256', $school->id.'-teacher-'.$index),
                    'school_id' => $school->id,
                    'school_resolution_status' => 'resolved',
                    'district' => $school->district,
                ]);
            }
        }
        Http::fake(['*' => Http::response(['error' => 'offline'], 503)]);

        $cases = [
            ['Berapa jumlah sekolah di setiap jenjang pendidikan?', 'school', 'education_level_breakdown', ['district' => null], 'bar_chart'],
            ['Sebutkan 5 distrik dengan jumlah sekolah terbanyak.', 'school', 'district_breakdown', ['limit' => 5], 'bar_chart'],
            ['Berapa jumlah sekolah di Distrik Manimeri berdasarkan jenjang?', 'school', 'education_level_breakdown', ['district' => 'Manimeri'], 'bar_chart'],
            ['Tampilkan SMP Negeri di Distrik Bintuni.', 'school', 'school_list', ['education_level' => 'SMP', 'status' => 'NEGERI', 'district' => 'Bintuni'], 'table'],
            ['Tampilkan SMA di Kabupaten Teluk Bintuni.', 'school', 'school_list', ['education_level' => 'SMA'], 'table'],
            ['Berapa jumlah guru di setiap jenjang pendidikan?', 'teacher', 'breakdown', ['group_by' => 'education_level'], 'bar_chart'],
            ['Sebutkan 5 sekolah dengan jumlah guru terbanyak.', 'teacher', 'ranking', ['group_by' => 'school', 'top_n' => 5], 'bar_chart'],
            ['Berapa jumlah guru yang mengajar di Distrik Manimeri?', 'teacher', 'count', ['district' => 'manimeri'], 'kpi'],
            ['Berapa jumlah guru SMP di Distrik Bintuni?', 'teacher', 'count', ['education_level' => 'SMP', 'district' => 'bintuni'], 'kpi'],
            ['Tampilkan 5 SMP dengan jumlah guru terbanyak.', 'teacher', 'ranking', ['group_by' => 'school', 'education_level' => 'SMP', 'top_n' => 5], 'bar_chart'],
        ];

        foreach ($cases as [$question, $domain, $operation, $expected, $presentation]) {
            $json = $this->postJson('/api/tifa/ask', ['question' => $question])->assertOk()->json();
            $this->assertSame($presentation, $json['presentation']['type'], $question);
            $this->assertStringNotContainsString('Berdasarkan data pendidikan terintegrasi', $json['answer'], $question);
            if ($domain === 'school') {
                $this->assertSame($operation, $json['intent']['action'], $question);
                foreach ($expected as $key => $value) {
                    $path = $key === 'limit' ? 'intent.options.limit' : 'intent.filters.'.$key;
                    $this->assertSame($value, data_get($json, $path), $question);
                }
            } else {
                $this->assertSame('teacher_analytics', $json['intent']['type'], $question);
                $this->assertSame($operation, $json['intent']['operation'], $question);
                foreach ($expected as $key => $value) {
                    $path = in_array($key, ['group_by', 'top_n'], true) ? 'intent.'.$key : 'intent.filters.'.$key;
                    $this->assertSame($value, data_get($json, $path), $question);
                }
            }
            if (in_array($operation, ['ranking', 'district_breakdown'], true)) {
                $this->assertCount(5, $json['data']['records'], $question);
                $this->assertCount(5, $json['presentation']['data'], $question);
            }
            if ($operation === 'school_list') {
                $this->assertNotEmpty($json['presentation']['rows'], $question);
                $this->assertArrayHasKey('name', $json['presentation']['rows'][0], $question);
            }
        }
        Http::assertNothingSent();
    }
}
