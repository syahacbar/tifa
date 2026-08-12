<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TifaSchoolListIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_list_and_count_phrasing_are_distinguished_without_ollama(): void
    {
        $dataset = Dataset::factory()->active()->create();
        foreach (['SMP Alfa', 'SMP Beta', 'SMP Cakra', 'SMP Delta'] as $index => $name) {
            School::factory()->for($dataset)->create(['name' => $name, 'education_level' => 'SMP', 'district' => 'Manimeri', 'status' => $index % 2 === 0 ? 'Negeri' : 'Swasta']);
        }
        School::factory()->for($dataset)->create(['name' => 'SD Bintuni 1', 'education_level' => 'SD', 'district' => 'Bintuni', 'status' => 'Negeri']);
        School::factory()->for($dataset)->create(['name' => 'SD Bintuni 2', 'education_level' => 'SD', 'district' => 'Bintuni', 'status' => 'Swasta']);
        Http::fake(['*' => Http::response(['error' => 'offline'], 503)]);

        foreach (['SMP di distrik Manimeri', 'Daftar SMP di distrik Manimeri', 'Apa saja SMP di Manimeri?', 'SD di Bintuni', 'Sekolah negeri di Manimeri'] as $question) {
            $response = $this->postJson('/api/tifa/ask', ['question' => $question])->assertOk();
            $response->assertJsonPath('intent.action', 'school_list')
                ->assertJsonPath('presentation.type', 'table');
            $this->assertNotEmpty($response->json('data.records'), $question);
            $this->assertSame(['no', 'name', 'education_level', 'status', 'district'], array_column($response->json('presentation.columns'), 'key'));
        }

        $nominal = $this->postJson('/api/tifa/ask', ['question' => 'SMP di distrik Manimeri'])->assertOk();
        $this->assertSame(4, $nominal->json('data.total'));
        $this->assertSame('Terdapat 4 sekolah jenjang SMP di Distrik Manimeri. Berikut daftarnya:', $nominal->json('answer'));
        $this->assertSame([1, 2, 3, 4], array_column($nominal->json('presentation.rows'), 'no'));

        foreach ([['Berapa SMP di distrik Manimeri?', 4], ['Berapa SD di Bintuni?', 2], ['Jumlah sekolah negeri di Manimeri', 2]] as [$question, $value]) {
            $this->postJson('/api/tifa/ask', ['question' => $question])
                ->assertOk()
                ->assertJsonPath('intent.action', 'school_count')
                ->assertJsonPath('data.value', $value);
        }
        Http::assertNothingSent();
    }
}
