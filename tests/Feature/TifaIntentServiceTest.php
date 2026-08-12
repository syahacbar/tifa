<?php

namespace Tests\Feature;

use App\Services\OllamaClient;
use App\Services\TifaIntentService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TifaIntentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tifa_ai.provider' => 'ollama',
            'services.tifa_ai.ollama.base_url' => 'http://ollama.test',
            'services.tifa_ai.ollama.model' => 'qwen3:4b',
            'services.tifa_ai.ollama.timeout' => 5,
        ]);
    }

    public function test_it_parses_a_basic_intent_from_ollama_json(): void
    {
        Http::fake([
            'http://ollama.test/api/generate' => Http::response([
                'response' => '{"action":"school_count","filters":{"education_level":"SD","status":"NEGERI","district":"MANIMERI"}}',
            ]),
        ]);

        $intent = app(TifaIntentService::class)->parse('Berapa jumlah SD negeri di Manimeri?');

        $this->assertSame([
            'action' => 'school_count',
            'filters' => [
                'education_level' => 'SD',
                'status' => 'NEGERI',
                'district' => 'MANIMERI',
            ],
        ], $intent);
        Http::assertSent(fn ($request) => $request->url() === 'http://ollama.test/api/generate'
            && $request['model'] === 'qwen3:4b'
            && $request['stream'] === false
            && $request['think'] === false
            && $request['format'] === 'json');
    }

    public function test_it_rejects_invalid_intent_json_safely(): void
    {
        Http::fake([
            '*' => Http::response(['response' => 'bukan JSON']),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bukan JSON yang valid');

        app(TifaIntentService::class)->parse('Berapa jumlah sekolah?');
    }

    public function test_it_does_not_treat_the_regency_name_as_a_district_filter(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => '{"action":"school_count","filters":{"education_level":"SD","status":null,"district":"TELUK BINTUNI"}}',
            ]),
        ]);

        $intent = app(TifaIntentService::class)->parse('Berapa jumlah SD di Kabupaten Teluk Bintuni?');

        $this->assertNull($intent['filters']['district']);
    }

    public function test_it_parses_validated_ranking_options(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => '{"action":"school_ranking","filters":{"education_level":"SD","status":null,"district":null},"options":{"ranking_by":"students_total","limit":10}}',
            ]),
        ]);

        $intent = app(TifaIntentService::class)->parse('10 SD dengan siswa terbanyak.');

        $this->assertSame('school_ranking', $intent['action']);
        $this->assertSame(['ranking_by' => 'students_total', 'limit' => 10], $intent['options']);
    }

    public function test_it_rejects_an_unsupported_action_from_ollama(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => '{"action":"write_sql","filters":{}}',
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak sesuai schema TIFA');

        app(TifaIntentService::class)->parse('Tampilkan semua sekolah.');
    }

    public function test_it_reports_ollama_model_errors_clearly(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'model "qwen3:4b" not found'], 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('model "qwen3:4b" not found');

        app(TifaIntentService::class)->parse('Berapa jumlah sekolah?');
    }

    public function test_health_check_reports_a_healthy_ollama_connection(): void
    {
        Http::fake([
            'http://ollama.test/api/tags' => Http::response(['models' => []]),
        ]);

        $health = app(OllamaClient::class)->health();

        $this->assertSame([
            'healthy' => true,
            'base_url' => 'http://ollama.test',
            'model' => 'qwen3:4b',
            'error' => null,
        ], $health);
    }

    public function test_health_check_returns_a_clear_error_when_ollama_is_unavailable(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $health = app(OllamaClient::class)->health();

        $this->assertFalse($health['healthy']);
        $this->assertSame('Ollama merespons HTTP 503.', $health['error']);
    }
}
