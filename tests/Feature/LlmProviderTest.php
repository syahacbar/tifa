<?php

namespace Tests\Feature;

use App\Contracts\LlmProvider;
use App\Exceptions\LlmProviderException;
use App\Services\GroqLlmProvider;
use App\Services\OllamaLlmProvider;
use App\Services\TifaAssistantService;
use App\Models\Dataset;
use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmProviderTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.tifa_ai.groq.api_key' => 'test-groq-key',
            'services.tifa_ai.groq.base_url' => 'https://groq.test/openai/v1',
            'services.tifa_ai.groq.model' => 'llama-test',
            'services.tifa_ai.groq.timeout' => 15,
        ]);
    }

    public function test_groq_provider_sends_configured_model_and_authorization_header(): void
    {
        Http::fake(['https://groq.test/openai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"action":"school_count"}']]],
        ])]);

        $content = app(GroqLlmProvider::class)->chat([
            ['role' => 'system', 'content' => 'intent only'],
            ['role' => 'user', 'content' => 'berapa sekolah'],
        ], ['json' => true]);

        $this->assertSame('{"action":"school_count"}', $content);
        Http::assertSent(fn ($request) => $request->url() === 'https://groq.test/openai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-groq-key')
            && $request['model'] === 'llama-test'
            && $request['temperature'] === 0
            && $request['response_format'] === ['type' => 'json_object']
            && ! str_contains(json_encode($request->data()), 'NIK'));
    }

    public function test_groq_provider_handles_rate_limit_server_error_and_malformed_response(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push(['error' => ['message' => 'slow down']], 429)
            ->push(['error' => ['message' => 'server failed']], 500)
            ->push(['choices' => []], 200)]);

        foreach ([
            'rate limited',
            'HTTP 500',
            'choices[0].message.content',
        ] as $message) {
            try {
                app(GroqLlmProvider::class)->chat([['role' => 'user', 'content' => 'test']]);
                $this->fail('Expected provider exception.');
            } catch (LlmProviderException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    public function test_provider_binding_resolves_groq_or_ollama_centrally(): void
    {
        config(['services.tifa_ai.provider' => 'groq']);
        $this->assertInstanceOf(GroqLlmProvider::class, app(LlmProvider::class));

        app()->forgetInstance(LlmProvider::class);
        config(['services.tifa_ai.provider' => 'ollama']);
        $this->assertInstanceOf(OllamaLlmProvider::class, app(LlmProvider::class));
    }

    public function test_fallback_school_intent_uses_groq_provider_but_teacher_queries_remain_local(): void
    {
        config(['services.tifa_ai.provider' => 'groq']);
        $dataset = Dataset::factory()->active()->create();
        School::factory()->count(3)->for($dataset)->create();
        Http::fake(['https://groq.test/openai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => '{"action":"school_count","filters":{"education_level":null,"status":null,"district":null}}']]],
        ])]);

        $result = app(TifaAssistantService::class)->ask('Tolong tampilkan total satuan pendidikan.');

        $this->assertSame('school_count', $result['intent']['action']);
        $this->assertSame(3, $result['data']['value']);
        Http::assertSent(fn ($request) => $request->url() === 'https://groq.test/openai/v1/chat/completions');
    }

    public function test_deterministic_teacher_analytics_does_not_call_groq(): void
    {
        config(['services.tifa_ai.provider' => 'groq']);
        $batch = TeacherImportBatch::create(['source_filename' => 'teacher.xlsx', 'source_checksum' => hash('sha256', 'teacher'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => 1, 'source_fingerprint' => hash('sha256', 'source'), 'deduplication_fingerprint' => hash('sha256', 'teacher'), 'school_resolution_status' => 'resolved', 'employment_status' => 'PPPK']);
        Http::fake();

        $result = app(TifaAssistantService::class)->ask('Berapa guru PPPK di SD?');

        $this->assertSame('teacher_analytics', $result['intent']['type']);
        $this->assertSame(1, $result['data']['value']);
        Http::assertNothingSent();
    }
}
