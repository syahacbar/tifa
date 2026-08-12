<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Services\TifaAssistantService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TifaGreetingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_simple_greetings_are_answered_locally_without_ollama(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 08:00:00', 'Asia/Jayapura'));
        Http::fake();
        $assistant = app(TifaAssistantService::class);

        foreach (['Halo TIFAA', 'Hai TIFAA', 'halo', 'Pagi TIFAA', 'Selamat pagi TIFAA', 'Assalamualaikum TIFAA', 'HALO TIFAA!!!'] as $question) {
            $result = $assistant->ask($question);
            $this->assertSame('greeting', $result['intent']['type']);
            $this->assertStringContainsString('selamat pagi', $result['answer']);
            $this->assertStringContainsString('TIFAA, Tata Kelola dan Informasi Pendidikan Terintegrasi', $result['answer']);
            $this->assertStringContainsString('Saya dapat membantu memberikan informasi seputar pendidikan, seperti sekolah, guru, siswa dan data pendidikan lainnya.', $result['answer']);
            $this->assertStringContainsString('Apa yang ingin Anda ketahui?', $result['answer']);
        }

        Http::assertNothingSent();
    }

    public function test_greeting_followed_by_a_data_question_continues_to_the_local_data_intent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 13:00:00', 'Asia/Jayapura'));
        $dataset = Dataset::factory()->active()->create();
        School::factory()->count(2)->for($dataset)->create(['status' => 'Negeri']);
        Http::fake();

        $result = app(TifaAssistantService::class)->ask('Halo TIFAA, berapa jumlah sekolah?');

        $this->assertSame('school_count', $result['intent']['action']);
        $this->assertSame(2, $result['data']['value']);
        $this->assertStringContainsString('Kabupaten Teluk Bintuni memiliki 2 sekolah', $result['answer']);
        Http::assertNothingSent();
    }
}
