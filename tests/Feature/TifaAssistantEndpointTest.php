<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
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
        School::factory()->count(88)->for($dataset)->create(['education_level' => 'SD']);

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
            'answer' => 'Berdasarkan Data Pendidikan Terintegrasi Teluk Bintuni, terdapat 88 Sekolah Dasar di Kabupaten Teluk Bintuni.',
            'data' => ['value' => 88],
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
            ->assertExactJson(['message' => 'Pertanyaan tidak dapat dipahami sebagai query data TIFA.']);
    }

    public function test_it_returns_a_safe_error_when_ollama_fails(): void
    {
        Http::fake(['*' => Http::response(['error' => 'server unavailable'], 503)]);

        $this->postJson('/api/tifa/ask', ['question' => 'Apa tugas seorang guru?'])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Layanan Ollama tidak tersedia.']);
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
        School::factory()->for($dataset)->create(['education_level' => 'SMP', 'status' => 'Negeri', 'district' => 'Manimeri', 'teachers' => 6, 'laboratories' => 3]);
        School::factory()->for($dataset)->create(['education_level' => 'SMA', 'status' => 'Swasta', 'district' => 'Sumuri', 'libraries' => 4]);
        Http::fake(['*' => Http::response(['error' => 'server unavailable'], 503)]);

        foreach ([
            ['Berapa jumlah sekolah di Kabupaten Teluk Bintuni?', 'school_count', 4],
            ['Berapa jumlah SD di Kabupaten Teluk Bintuni?', 'school_count', 2],
            ['Berapa jumlah sekolah negeri?', 'school_count', 2],
            ['Berapa jumlah sekolah swasta?', 'school_count', 2],
            ['Berapa jumlah sekolah di Distrik Bintuni?', 'school_count', 2],
            ['Berapa total siswa SD?', 'student_total', 30],
            ['Berapa jumlah guru sekolah negeri?', 'teacher_total', 8],
            ['Berapa laboratorium SMP?', 'laboratory_total', 3],
            ['Berapa jumlah ruang kelas SD?', 'classroom_total', 8],
            ['Berapa jumlah perpustakaan SMA?', 'library_total', 4],
        ] as [$question, $action, $value]) {
            $this->postJson('/api/tifa/ask', ['question' => $question])
                ->assertOk()
                ->assertJsonPath('intent.action', $action)
                ->assertJsonPath('data.value', $value);
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
            ->assertExactJson(['message' => 'Dataset aktif TIFA tidak tersedia.']);
    }
}
