<?php

namespace Tests\Feature;

use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TeacherEmploymentStatusComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_employment_status_comparisons_are_deterministic_and_include_the_verified_pppk_family(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'employment-status.xlsx', 'source_checksum' => hash('sha256', 'employment-status'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach (['PNS', 'PPPK', 'PPPK Tahap II', 'Guru Honor Sekolah'] as $index => $status) {
            TeacherAssignment::create([
                'teacher_import_batch_id' => $batch->id,
                'source_sheet' => 'SD',
                'source_row' => $index + 1,
                'source_fingerprint' => hash('sha256', 'employment-source-'.$index),
                'deduplication_fingerprint' => hash('sha256', 'employment-teacher-'.$index),
                'school_resolution_status' => 'resolved',
                'employment_status' => $status,
            ]);
        }
        Http::fake();

        foreach ([
            ['Berapa jumlah guru PNS, PPPK, dan kontrak?', true],
            ['Berapa guru PNS, P3K dan kontrak?', true],
            ['Jumlah guru PNS dan PPPK berapa?', false],
        ] as [$question, $expectsContractNotice]) {
            $response = $this->postJson('/api/tifa/ask', ['question' => $question])->assertOk();
            $response->assertJsonPath('intent.type', 'teacher_analytics')
                ->assertJsonPath('intent.operation', 'comparison')
                ->assertJsonPath('intent.metric', 'unique_teacher_count')
                ->assertJsonPath('intent.group_by', 'employment_status')
                ->assertJsonPath('intent.comparison_values', ['PNS', 'PPPK'])
                ->assertJsonPath('presentation.type', 'bar_chart');
            $this->assertSame([
                ['label' => 'PNS', 'value' => 1],
                ['label' => 'PPPK', 'value' => 2],
            ], $response->json('data.records'));
            $this->assertSame($response->json('data.records'), $response->json('presentation.data'));
            $this->assertSame($expectsContractNotice, str_contains($response->json('answer'), 'Kategori guru kontrak tidak tersedia'));
        }

        Http::assertNothingSent();
    }
}
