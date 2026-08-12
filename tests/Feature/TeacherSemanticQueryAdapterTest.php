<?php

namespace Tests\Feature;

use App\Exceptions\SemanticQueryValidationException;
use App\Models\TeacherAssignment;
use App\Models\TeacherImportBatch;
use App\Queries\SemanticQuery;
use App\Services\TeacherAnalyticsIntentService;
use App\Services\TeacherSemanticQueryAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSemanticQueryAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_parser_emits_valid_v2_ranking_contracts(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'districts.xlsx', 'source_checksum' => hash('sha256', 'districts'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        foreach (['Bintuni', 'Manimeri'] as $index => $district) {
            TeacherAssignment::create(['teacher_import_batch_id' => $batch->id, 'source_sheet' => 'SD', 'source_row' => $index + 1, 'source_fingerprint' => hash('sha256', $district), 'deduplication_fingerprint' => hash('sha256', 'teacher-'.$district), 'school_resolution_status' => 'resolved', 'district' => $district]);
        }
        $parser = app(TeacherAnalyticsIntentService::class);

        $this->assertSemantic($parser->parseSemantic('Tampilkan 5 sekolah setingkat SMA dengan jumlah guru terbanyak'), 'school', ['education_level' => 'SMA'], 'desc', 5);
        $this->assertSemantic($parser->parseSemantic('Guru terbanyak ada di distrik mana?'), 'district', ['district' => null], 'desc', 1);
        $this->assertSemantic($parser->parseSemantic('Guru SD paling banyak ada di distrik mana?'), 'district', ['education_level' => 'SD', 'district' => null], 'desc', 1);
        $this->assertSemantic($parser->parseSemantic('Sekolah dengan guru terbanyak di Distrik Manimeri'), 'school', ['district' => 'manimeri'], 'desc', 1);
        $this->assertSemantic($parser->parseSemantic('Sebutkan 5 sekolah dengan tenaga pendidik terbanyak di Bintuni'), 'school', ['district' => 'bintuni'], 'desc', 5);
        $this->assertSemantic($parser->parseSemantic('Sekolah mana yang gurunya paling sedikit?'), 'school', [], 'asc', 1);
        $this->assertSemantic($parser->parseSemantic('Sekolah mana yang gurunya tertinggi?'), 'school', [], 'desc', 1);
        $this->assertSemantic($parser->parseSemantic('Sekolah mana yang gurunya terendah?'), 'school', [], 'asc', 1);
    }

    public function test_adapter_maps_v2_teacher_ranking_to_the_existing_v1_tool_contract(): void
    {
        $semantic = new SemanticQuery('teacher', 'ranking', 'unique_teacher_count', 'school', ['education_level' => 'SMA'], ['field' => 'value', 'direction' => 'desc'], 5);
        $contract = app(TeacherSemanticQueryAdapter::class)->toToolContract($semantic);

        $this->assertSame('v1', $contract['version']);
        $this->assertSame('ranking', $contract['operation']);
        $this->assertSame('teacher_identity', $contract['entity']);
        $this->assertSame('unique_teacher_count', $contract['metric']);
        $this->assertSame('SMA', $contract['filters']['education_level']);
        $this->assertSame('school', $contract['group_by']);
        $this->assertSame(5, $contract['top_n']);
        $this->assertArrayNotHasKey('domain', $contract);
    }

    public function test_adapter_preserves_ascending_sort_for_v2_3_execution(): void
    {
        $contract = app(TeacherSemanticQueryAdapter::class)->toToolContract(
            new SemanticQuery('teacher', 'ranking', 'unique_teacher_count', 'school', sort: ['field' => 'value', 'direction' => 'asc'], limit: 1),
        );

        $this->assertSame(['field' => 'value', 'direction' => 'asc'], $contract['sort']);
    }

    public function test_ascending_teacher_question_no_longer_returns_the_v2_2_unsupported_error(): void
    {
        $batch = TeacherImportBatch::create(['source_filename' => 'ascending.xlsx', 'source_checksum' => hash('sha256', 'ascending'), 'reference_period' => 'Maret 2026', 'is_authoritative' => true]);
        $this->postJson('/api/tifa/ask', ['question' => 'Sekolah mana yang gurunya paling sedikit?'])
            ->assertOk()
            ->assertJsonPath('intent.sort.direction', 'asc');
    }

    /** @param array<string, mixed> $expectedFilters */
    private function assertSemantic(?SemanticQuery $query, string $groupBy, array $expectedFilters, string $direction, int $limit): void
    {
        $this->assertInstanceOf(SemanticQuery::class, $query);
        $this->assertSame('v2', $query->toArray()['version']);
        $this->assertSame('teacher', $query->domain);
        $this->assertSame('ranking', $query->operation);
        $this->assertSame('unique_teacher_count', $query->metric);
        $this->assertSame($groupBy, $query->groupBy);
        foreach ($expectedFilters as $field => $value) $this->assertSame($value, $query->filters[$field]);
        $this->assertSame(['field' => 'value', 'direction' => $direction], $query->sort);
        $this->assertSame($limit, $query->limit);
    }
}
