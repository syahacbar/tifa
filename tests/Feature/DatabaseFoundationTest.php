<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dataset_has_many_schools_with_expected_casts(): void
    {
        $dataset = Dataset::factory()->create([
            'published_at' => '2026-08-11',
            'metadata' => ['source' => 'test'],
        ]);
        $school = School::factory()->for($dataset)->create();

        $this->assertTrue($dataset->schools->contains($school));
        $this->assertTrue($school->dataset->is($dataset));
        $this->assertSame(['source' => 'test'], $dataset->metadata);
        $this->assertSame('2026-08-11', $dataset->published_at->toDateString());
        $this->assertIsInt($school->students_total);
    }

    public function test_source_key_is_unique_within_a_dataset(): void
    {
        $dataset = Dataset::factory()->create();
        School::factory()->for($dataset)->create([
            'npsn' => '60401234',
            'name' => 'SD Inpres Bintuni',
            'education_level' => 'SD',
            'district' => 'Bintuni',
        ]);

        $this->expectException(QueryException::class);

        School::factory()->for($dataset)->create([
            'npsn' => '60409999',
            'name' => ' sd inpres bintuni ',
            'education_level' => 'sd',
            'district' => 'BINTUNI',
        ]);
    }

    public function test_deleting_a_dataset_cascades_to_its_schools(): void
    {
        $dataset = Dataset::factory()->create();
        $school = School::factory()->for($dataset)->create();

        $dataset->delete();

        $this->assertModelMissing($school);
    }
}
