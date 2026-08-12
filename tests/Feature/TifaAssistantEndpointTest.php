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
            'answer' => 'Berdasarkan Data Dapodik Juni 2026, terdapat 88 Sekolah Dasar di Kabupaten Teluk Bintuni.',
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

        $this->postJson('/api/tifa/ask', ['question' => 'Berapa jumlah sekolah?'])
            ->assertStatus(503)
            ->assertExactJson(['message' => 'Layanan Ollama tidak tersedia.']);
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
