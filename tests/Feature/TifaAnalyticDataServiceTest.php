<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Services\TifaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TifaAnalyticDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private TifaDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TifaDataService::class);
    }

    public function test_it_lists_schools_using_the_requested_filters(): void
    {
        $this->createDataset();

        $result = $this->service->query([
            'action' => 'school_list',
            'filters' => ['education_level' => 'SMP', 'status' => 'NEGERI', 'district' => 'BABO'],
        ]);

        $this->assertSame('table', $result['visualization']);
        $this->assertSame(1, $result['data']['total']);
        $this->assertSame('SMP Babo', $result['data']['records'][0]['name']);
    }

    public function test_it_returns_a_ranking_with_a_validated_limit_and_metric(): void
    {
        $this->createDataset();

        $result = $this->service->query([
            'action' => 'school_ranking',
            'filters' => ['education_level' => 'SD'],
            'options' => ['ranking_by' => 'students_total', 'limit' => 2],
        ]);

        $this->assertSame('table', $result['visualization']);
        $this->assertSame('students_total', $result['data']['ranking_by']);
        $this->assertSame(['SD Besar', 'SD Kecil'], array_column($result['data']['records'], 'name'));
        $this->assertSame([1, 2], array_column($result['data']['records'], 'rank'));
    }

    public function test_it_rejects_ranking_limits_above_twenty(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->query([
            'action' => 'school_ranking',
            'options' => ['ranking_by' => 'students_total', 'limit' => 21],
        ]);
    }

    public function test_it_returns_a_district_breakdown_as_bar_chart_data(): void
    {
        $this->createDataset();

        $result = $this->service->query([
            'action' => 'district_breakdown',
            'filters' => ['education_level' => 'SD'],
        ]);

        $this->assertSame('bar_chart', $result['visualization']);
        $this->assertSame([
            ['label' => 'Babo', 'value' => 1],
            ['label' => 'Bintuni', 'value' => 2],
        ], $result['data']['records']);
    }

    public function test_it_returns_education_level_and_status_breakdowns(): void
    {
        $this->createDataset();

        $educationLevels = $this->service->query(['action' => 'education_level_breakdown']);
        $statuses = $this->service->query(['action' => 'status_breakdown']);

        $this->assertSame('bar_chart', $educationLevels['visualization']);
        $this->assertSame([
            ['label' => 'SD', 'value' => 3],
            ['label' => 'SMP', 'value' => 1],
        ], $educationLevels['data']['records']);
        $this->assertSame('comparison', $statuses['visualization']);
        $this->assertSame([
            ['label' => 'Negeri', 'value' => 3],
            ['label' => 'Swasta', 'value' => 1],
        ], $statuses['data']['records']);
    }

    private function createDataset(): void
    {
        $dataset = Dataset::factory()->active()->create();

        School::factory()->for($dataset)->create(['npsn' => '10000001', 'name' => 'SD Besar', 'education_level' => 'SD', 'district' => 'Bintuni', 'status' => 'Negeri', 'students_total' => 100]);
        School::factory()->for($dataset)->create(['npsn' => '10000002', 'name' => 'SD Kecil', 'education_level' => 'SD', 'district' => 'Bintuni', 'status' => 'Swasta', 'students_total' => 50]);
        School::factory()->for($dataset)->create(['npsn' => '10000003', 'name' => 'SD Babo', 'education_level' => 'SD', 'district' => 'Babo', 'status' => 'Negeri', 'students_total' => 40]);
        School::factory()->for($dataset)->create(['npsn' => '10000004', 'name' => 'SMP Babo', 'education_level' => 'SMP', 'district' => 'Babo', 'status' => 'Negeri', 'students_total' => 80]);
    }
}
