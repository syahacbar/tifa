<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Services\DapodikImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DapodikImportTest extends TestCase
{
    use RefreshDatabase;

    private string $workbook = 'storage/app/imports/rekap-dapodik-juni-2026.xlsx';

    public function test_numeric_npsn_is_accepted(): void
    {
        $school = School::factory()->create(['npsn' => '60401234']);

        $this->assertSame('60401234', $school->fresh()->npsn);
    }

    public function test_alphanumeric_npsn_is_accepted(): void
    {
        $school = School::factory()->create(['npsn' => 'P9947985']);

        $this->assertSame('P9947985', $school->fresh()->npsn);
    }

    public function test_two_different_schools_with_the_same_npsn_are_accepted(): void
    {
        $dataset = Dataset::factory()->create();
        School::factory()->for($dataset)->create([
            'npsn' => '60725746',
            'name' => 'SMPN TAROI',
            'education_level' => 'SMP',
            'district' => 'Tomu',
        ]);
        School::factory()->for($dataset)->create([
            'npsn' => '60725746',
            'name' => 'SMP NEGERI 1 TUHIBA',
            'education_level' => 'SMP',
            'district' => 'Tuhiba',
        ]);

        $this->assertSame(2, $dataset->schools()->where('npsn', '60725746')->count());
        $this->assertSame(2, $dataset->schools()->distinct('source_key')->count('source_key'));
    }

    public function test_dry_run_does_not_write_to_the_database(): void
    {
        $this->requireWorkbook();

        $this->artisan('tifa:import-dapodik', [
            'file' => $this->workbook,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('datasets', 0);
        $this->assertDatabaseCount('schools', 0);
    }

    public function test_normal_import_creates_one_dataset_and_all_unique_schools(): void
    {
        $this->requireWorkbook();

        $this->artisan('tifa:import-dapodik', ['file' => $this->workbook])
            ->assertSuccessful();

        $dataset = Dataset::query()->sole();

        $this->assertSame(DapodikImportService::DATASET_NAME, $dataset->name);
        $this->assertSame(DapodikImportService::REFERENCE_PERIOD, $dataset->reference_period);
        $this->assertTrue($dataset->is_active);
        $this->assertSame(290, $dataset->schools()->count());
        $this->assertSame(290, $dataset->schools()->distinct('source_key')->count('source_key'));
        $this->assertSame(2, $dataset->schools()->where('npsn', '60725746')->count());
        $this->assertTrue($dataset->schools()->where('npsn', 'P9947985')->exists());

        $this->artisan('tifa:import-dapodik', ['file' => $this->workbook])
            ->assertSuccessful();

        $this->assertDatabaseCount('datasets', 1);
        $this->assertDatabaseCount('schools', 290);
    }

    private function requireWorkbook(): void
    {
        if (! is_file(base_path($this->workbook))) {
            $this->markTestSkipped('Workbook Dapodik privat tidak tersedia.');
        }
    }
}
