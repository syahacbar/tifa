<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolQueryScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_the_latest_active_dataset(): void
    {
        Dataset::factory()->create(['published_at' => '2026-08-11']);
        $older = Dataset::factory()->active()->create(['published_at' => '2025-08-11']);
        $current = Dataset::factory()->active()->create(['published_at' => '2026-08-11']);

        $this->assertTrue(Dataset::current()->is($current));
        $this->assertCount(2, Dataset::active()->get());
        $this->assertFalse($older->is(Dataset::current()));
    }

    public function test_school_filters_are_trimmed_case_insensitive_and_composable(): void
    {
        $activeDataset = Dataset::factory()->active()->create();
        $inactiveDataset = Dataset::factory()->create();

        $match = School::factory()->for($activeDataset)->create([
            'education_level' => 'SD',
            'district' => 'Bintuni',
            'status' => 'Negeri',
        ]);
        School::factory()->for($activeDataset)->create([
            'education_level' => 'SMP',
            'district' => 'Bintuni',
            'status' => 'Negeri',
        ]);
        School::factory()->for($activeDataset)->create([
            'education_level' => 'SD',
            'district' => 'Merdey',
            'status' => 'Swasta',
        ]);
        School::factory()->for($inactiveDataset)->create([
            'education_level' => 'SD',
            'district' => 'Bintuni',
            'status' => 'Negeri',
        ]);

        $schools = School::query()
            ->fromActiveDataset()
            ->byEducationLevel(' sd ')
            ->byDistrict(' BINTUNI ')
            ->byStatus('negeri')
            ->get();

        $this->assertCount(1, $schools);
        $this->assertTrue($schools->first()->is($match));
    }

    public function test_empty_filters_do_not_restrict_the_query(): void
    {
        $dataset = Dataset::factory()->active()->create();
        School::factory()->count(2)->for($dataset)->create();

        $schools = School::query()
            ->fromActiveDataset()
            ->byEducationLevel(null)
            ->byDistrict('   ')
            ->byStatus('')
            ->get();

        $this->assertCount(2, $schools);
    }
}
