<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TifaAssistantEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tifa_ai.provider' => 'ollama',
            'services.tifa_ai.ollama.base_url' => 'http://ollama.test',
            'services.tifa_ai.ollama.model' => 'qwen3:4b',
        ]);
    }

    public function test_it_returns_an_orchestrated_answer_from_the_active_dataset(): void
    {
        $dataset = Dataset::factory()->active()->create([
            'name' => 'Rekap Dapodik Juni 2026',
            'reference_period' => 'Semester 2 Tahun Pelajaran 2025/2026',
            'published_at' => '2026-06-30',
        ]);
        School::factory()->count(44)->for($dataset)->create(['education_level' => 'SD', 'status' => 'Negeri']);
        School::factory()->count(44)->for($dataset)->create(['education_level' => 'SD', 'status' => 'Swasta']);

        Http::fake([
            '*' => Http::response([
                'response' => '{"action":"school_count","filters":{"education_level":"SD","status":null,"district":null}}',
            ]),
        ]);

        $this->postJson('/api/tifa/ask', [
            'question' => 'Berapa jumlah SD di Kabupaten Teluk Bintuni?',
        ])->assertOk()->assertExactJson([
            'question' => 'Berapa jumlah SD di Kabupaten Teluk Bintuni?',
            'intent' => [
                'action' => 'school_count',
                'filters' => [
                    'education_level' => 'SD',
                    'status' => null,
                    'district' => null,
                ],
            ],
            'answer' => 'Jumlah SD di Kabupaten Teluk Bintuni sebanyak 88 sekolah, terdiri dari 44 sekolah negeri dan 44 sekolah swasta.',
            'data' => ['value' => 88],
            'presentation' => [
                'type' => 'kpi',
                'title' => 'Jumlah Sekolah',
                'value' => 88,
                'unit' => 'sekolah',
            ],
            'visualization' => 'kpi',
            'source' => [
                'dataset' => 'Rekap Dapodik Juni 2026',
                'reference_period' => 'Semester 2 Tahun Pelajaran 2025/2026',
                'source_date' => '2026-06-30',
            ],
        ]);
    }

    public function test_it_validates_the_question_input(): void
    {
        $this->postJson('/api/tifa/ask', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('question');
    }

    public function test_it_returns_a_safe_error_when_intent_is_invalid(): void
    {
        Http::fake(['*' => Http::response(['response' => '{"action":"write_sql","filters":{}}'])]);

        $this->postJson('/api/tifa/ask', ['question' => 'Tampilkan semua sekolah.'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Pertanyaan tidak dapat dipahami sebagai query data TIFAA.']);
    }

    public function test_it_returns_a_safe_error_when_ollama_fails(): void
    {
        Http::fake(['*' => Http::response(['error' => 'server unavailable'], 503)]);

        $this->postJson('/api/tifa/ask', ['question' => 'Apa tugas seorang guru?'])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Layanan AI tidak tersedia.']);
    }

    public function test_local_school_quick_question_succeeds_when_ollama_is_unavailable(): void
    {
        $dataset = Dataset::factory()->active()->create();
        School::factory()->count(2)->for($dataset)->create(['education_level' => 'SD', 'students_total' => 15]);
        Http::fake(['*' => Http::response(['error' => 'server unavailable'], 503)]);

        $this->postJson('/api/tifa/ask', ['question' => 'Berapa total siswa SD?'])
            ->assertOk()
            ->assertJsonPath('intent.action', 'student_total')
            ->assertJsonPath('data.value', 30);

        Http::assertNothingSent();
    }

    public function test_local_teacher_school_quick_question_succeeds_when_ollama_is_unavailable(): void
    {
        $dataset = Dataset::factory()->active()->create();
        School::factory()->for($dataset)->create(['status' => 'Negeri', 'teachers' => 7]);
        School::factory()->for($dataset)->create(['status' => 'Swasta', 'teachers' => 11]);
        Http::fake(['*' => Http::response(['error' => 'server unavailable'], 503)]);

        $this->postJson('/api/tifa/ask', ['question' => 'Berapa jumlah guru sekolah negeri?'])
            ->assertOk()
            ->assertJsonPath('intent.action', 'teacher_total')
            ->assertJsonPath('intent.filters.status', 'NEGERI')
            ->assertJsonPath('data.value', 7);

        Http::assertNothingSent();
    }

    public function test_all_default_quick_questions_are_answered_locally_when_ollama_is_unavailable(): void
    {
        $dataset = Dataset::factory()->active()->create();
        School::factory()->for($dataset)->create(['education_level' => 'SD', 'status' => 'Negeri', 'district' => 'Bintuni', 'students_total' => 10, 'teachers' => 2, 'classrooms' => 3]);
        School::factory()->for($dataset)->create(['education_level' => 'SD', 'status' => 'Swasta', 'district' => 'Bintuni', 'students_total' => 20, 'teachers' => 4, 'classrooms' => 5]);
        School::factory()->for($dataset)->create(['education_level' => 'SMP', 'status' => 'Negeri', 'district' => 'Manimeri', 'students_total' => 0, 'teachers' => 6, 'laboratories' => 3]);
        School::factory()->for($dataset)->create(['education_level' => 'SMA', 'status' => 'Swasta', 'district' => 'Sumuri', 'students_total' => 0, 'libraries' => 4]);
        $batch = TeacherImportBatch::create(['source_filename' => 'quick-questions.xlsx', 'source_checksum' => hash('sha256', 'quick-questions'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach ([['Bintuni', 'PNS'], ['Bintuni', 'PPPK'], ['Manimeri', 'PNS'], ['Babo', 'PPPK'], ['Tomu', 'PNS'], ['Sumuri', 'PPPK']] as $index => [$district, $status]) {
            TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => $index + 1, 'source_fingerprint' => hash('sha256', 'quick-'.$index), 'deduplication_fingerprint' => hash('sha256', 'teacher-'.$index), 'school_resolution_status' => 'resolved', 'district' => $district, 'employment_status' => $status]);
        }
        Http::fake(['*' => Http::response(['error' => 'server unavailable'], 503)]);

        foreach ([
            ['Berapa jumlah sekolah di Kabupaten Teluk Bintuni?', 'school_count', 4],
            ['Berapa jumlah sekolah di Distrik Bintuni?', 'school_count', 2],
            ['Berapa jumlah SD di Kabupaten Teluk Bintuni?', 'school_count', 2],
            ['Berapa jumlah sekolah negeri?', 'school_count', 2],
            ['Berapa jumlah siswa di Kabupaten Teluk Bintuni?', 'student_total', 30],
            ['Berapa total siswa SD?', 'student_total', 30],
            ['Berapa laboratorium SMP?', 'laboratory_total', 3],
        ] as [$question, $action, $value]) {
            $response = $this->postJson('/api/tifa/ask', ['question' => $question])
                ->assertOk()
                ->assertJsonPath('intent.action', $action)
                ->assertJsonPath('data.value', $value);
            $this->assertStringNotContainsString('Berdasarkan data pendidikan terintegrasi', $response->json('answer'));
        }

        foreach ([
            ['Berapa jumlah guru di Kabupaten Teluk Bintuni?', 'count', 6],
            ['Sebutkan 5 distrik dengan jumlah guru terbanyak.', 'ranking', null],
            ['Berapa jumlah guru PPPK di Kabupaten Teluk Bintuni?', 'count', 3],
        ] as [$question, $operation, $value]) {
            $response = $this->postJson('/api/tifa/ask', ['question' => $question])
                ->assertOk()
                ->assertJsonPath('intent.type', 'teacher_analytics')
                ->assertJsonPath('intent.operation', $operation);
            if ($value !== null) $response->assertJsonPath('data.value', $value);
            if ($operation === 'ranking') $this->assertCount(5, $response->json('data.records'));
            $this->assertStringNotContainsString('Berdasarkan data pendidikan terintegrasi', $response->json('answer'));
        }

        Http::assertNothingSent();
    }

    public function test_it_returns_a_safe_error_when_the_active_dataset_is_missing(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => '{"action":"school_count","filters":{"education_level":null,"status":null,"district":null}}',
            ]),
        ]);

        $this->postJson('/api/tifa/ask', ['question' => 'Berapa jumlah sekolah?'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Dataset aktif TIFAA tidak tersedia.']);
    }

    public function test_local_school_counts_are_answer_first_and_include_district_composition(): void
    {
        $dataset = Dataset::factory()->active()->create();
        School::factory()->count(7)->for($dataset)->create(['district' => 'Babo', 'education_level' => 'SD', 'status' => 'Negeri']);
        School::factory()->count(4)->for($dataset)->create(['district' => 'Babo', 'education_level' => 'SD', 'status' => 'Swasta']);
        School::factory()->count(38)->for($dataset)->create(['education_level' => 'SMP', 'status' => 'Negeri']);
        Http::fake();

        $district = $this->postJson('/api/tifa/ask', ['question' => 'Berapa jumlah sekolah di Distrik Babo?'])
            ->assertOk()
            ->assertJsonPath('data.value', 11);
        $this->assertSame('Distrik Babo memiliki 11 sekolah, terdiri dari 7 sekolah negeri dan 4 sekolah swasta.', $district->json('answer'));

        $level = $this->postJson('/api/tifa/ask', ['question' => 'Berapa jumlah SMP di Kabupaten Teluk Bintuni?'])
            ->assertOk()
            ->assertJsonPath('data.value', 38);
        $this->assertSame('Jumlah SMP di Kabupaten Teluk Bintuni sebanyak 38 sekolah, terdiri dari 38 sekolah negeri dan 0 sekolah swasta.', $level->json('answer'));
        $this->assertStringNotContainsString('Berdasarkan data', $level->json('answer'));
        Http::assertNothingSent();
    }
}
