<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Services\TifaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class TifaDataServiceTest extends TestCase
{
    use RefreshDatabase;

    private TifaDataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TifaDataService::class);
    }

    public function test_it_counts_schools_without_filters_on_the_active_dataset(): void
    {
        $dataset = $this->createActiveDatasetWithSchools();

        $result = $this->service->query(['action' => 'school_count', 'filters' => []]);

        $this->assertSame(3, $result['value']);
        $this->assertSame([
            'education_level' => null,
            'status' => null,
            'district' => null,
        ], $result['filters']);
        $this->assertSame([
            'id' => $dataset->id,
            'name' => 'Dataset Pengujian',
            'reference_period' => 'Semester 2 2025/2026',
        ], $result['dataset']);
        $this->assertSame('2026-06-30', $result['source_date']);
    }

    public function test_it_filters_by_education_level(): void
    {
        $this->createActiveDatasetWithSchools();

        $result = $this->service->query([
            'action' => 'student_total',
            'filters' => ['education_level' => ' sd '],
        ]);

        $this->assertSame(150, $result['value']);
    }

    public function test_it_filters_by_status(): void
    {
        $this->createActiveDatasetWithSchools();

        $result = $this->service->query([
            'action' => 'teacher_total',
            'filters' => ['status' => 'NEGERI'],
        ]);

        $this->assertSame(14, $result['value']);
    }

    public function test_it_filters_by_district(): void
    {
        $this->createActiveDatasetWithSchools();

        $result = $this->service->query([
            'action' => 'education_staff_total',
            'filters' => ['district' => 'bintuni'],
        ]);

        $this->assertSame(3, $result['value']);
    }

    public function test_it_combines_filters(): void
    {
        $this->createActiveDatasetWithSchools();

        $result = $this->service->query([
            'action' => 'classroom_total',
            'filters' => [
                'education_level' => 'SD',
                'status' => 'Negeri',
                'district' => 'Bintuni',
            ],
        ]);

        $this->assertSame(8, $result['value']);
    }

    public function test_it_rejects_an_unsupported_action(): void
    {
        try {
            $this->service->query(['action' => 'school_map']);
            $this->fail('ValidationException tidak dilemparkan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('action', $exception->errors());
        }
    }

    public function test_it_rejects_an_unsupported_filter(): void
    {
        try {
            $this->service->query([
                'action' => 'school_count',
                'filters' => ['village' => 'Bintuni'],
            ]);
            $this->fail('ValidationException tidak dilemparkan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('filters', $exception->errors());
        }
    }

    public function test_it_fails_when_an_active_dataset_is_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dataset aktif tidak ditemukan.');

        $this->service->query(['action' => 'school_count']);
    }

    private function createActiveDatasetWithSchools(): Dataset
    {
        $dataset = Dataset::factory()->active()->create([
            'name' => 'Dataset Pengujian',
            'reference_period' => 'Semester 2 2025/2026',
            'published_at' => '2026-06-30',
        ]);

        School::factory()->for($dataset)->create([
            'npsn' => '10000001',
            'education_level' => 'SD',
            'status' => 'Negeri',
            'district' => 'Bintuni',
            'students_male' => 55,
            'students_female' => 45,
            'students_total' => 100,
            'teachers' => 10,
            'education_staff' => 2,
            'classrooms' => 8,
        ]);
        School::factory()->for($dataset)->create([
            'npsn' => '10000002',
            'education_level' => 'SD',
            'status' => 'Swasta',
            'district' => 'Bintuni',
            'students_male' => 30,
            'students_female' => 20,
            'students_total' => 50,
            'teachers' => 5,
            'education_staff' => 1,
            'classrooms' => 4,
        ]);
        School::factory()->for($dataset)->create([
            'npsn' => '10000003',
            'education_level' => 'SMP',
            'status' => 'Negeri',
            'district' => 'Merdey',
            'students_male' => 25,
            'students_female' => 15,
            'students_total' => 40,
            'teachers' => 4,
            'education_staff' => 1,
            'classrooms' => 3,
        ]);

        $inactiveDataset = Dataset::factory()->create();
        School::factory()->for($inactiveDataset)->create(['students_total' => 999]);

        return $dataset;
    }
}
