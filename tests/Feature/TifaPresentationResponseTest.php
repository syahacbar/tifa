<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TifaPresentationResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_deterministic_responses_expose_authoritative_kpi_chart_and_table_presentations_without_ollama(): void
    {
        $dataset = Dataset::factory()->active()->create();
        foreach ([['Bintuni', 'Negeri'], ['Bintuni', 'Swasta'], ['Babo', 'Negeri'], ['Manimeri', 'Swasta'], ['Tomu', 'Negeri']] as $index => [$district, $status]) {
            School::factory()->for($dataset)->create(['name' => 'Sekolah '.$index, 'district' => $district, 'status' => $status, 'education_level' => $index === 2 ? 'SMP' : 'SD']);
        }
        $batch = TeacherImportBatch::create(['source_filename' => 'presentation.xlsx', 'source_checksum' => hash('sha256', 'presentation'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach ([['Bintuni', 'PNS', 5], ['Manimeri', 'PPPK', 4], ['Babo', 'PNS', 3], ['Tomu', 'PPPK', 2], ['Sumuri', 'PNS', 1]] as [$district, $status, $total]) {
            for ($index = 0; $index < $total; $index++) TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => random_int(1, 999999), 'source_fingerprint' => hash('sha256', uniqid('', true)), 'deduplication_fingerprint' => hash('sha256', $district.$status.$index), 'school_resolution_status' => 'resolved', 'district' => $district, 'employment_status' => $status]);
        }
        Http::fake(['*' => Http::response(['error' => 'offline'], 503)]);

        $this->assertKpi('Berapa jumlah guru di Kabupaten Teluk Bintuni?');
        $this->assertChart('Sebutkan 5 distrik dengan guru terbanyak.');
        $this->assertChart('Sebutkan 5 distrik dengan jumlah sekolah terbanyak.');
        $this->assertChart('Bandingkan jumlah guru Bintuni dan Manimeri');
        $this->assertChart('Berapa jumlah guru berdasarkan status kepegawaian?');
        $this->assertChart('Berapa jumlah sekolah negeri dan swasta?');
        $this->assertTable('Sebutkan sekolah di Distrik Babo');
        Http::assertNothingSent();
    }

    private function assertKpi(string $question): void
    {
        $json = $this->postJson('/api/tifa/ask', ['question' => $question])->assertOk()->json();
        $this->assertSame('kpi', $json['presentation']['type']);
        $this->assertSame($json['data']['value'], $json['presentation']['value']);
    }

    private function assertChart(string $question): void
    {
        $json = $this->postJson('/api/tifa/ask', ['question' => $question])->assertOk()->json();
        $this->assertSame('bar_chart', $json['presentation']['type'], $question);
        $this->assertSame($json['data']['records'], $json['presentation']['data'], $question);
    }

    private function assertTable(string $question): void
    {
        $json = $this->postJson('/api/tifa/ask', ['question' => $question])->assertOk()->json();
        $this->assertSame('table', $json['presentation']['type']);
        $this->assertSame(array_map(fn (array $row, int $index) => ['no' => $index + 1, ...$row], $json['data']['records'], array_keys($json['data']['records'])), $json['presentation']['rows']);
    }
}
