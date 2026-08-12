<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use App\Services\TifaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HomepageEducationSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_uses_active_dataset_and_reconciles_status_and_levels(): void
    {
        $dataset = Dataset::factory()->active()->create(['reference_period' => 'Semester 2 2025/2026']);
        School::factory()->for($dataset)->create(['education_level' => 'SD', 'status' => 'Negeri', 'district' => 'Bintuni']);
        School::factory()->for($dataset)->create(['education_level' => 'TK', 'status' => 'Swasta', 'district' => 'Bintuni']);
        School::factory()->for($dataset)->create(['education_level' => 'PKBM', 'status' => 'Swasta', 'district' => 'Merdey']);
        $summary = app(TifaDataService::class)->homepageSummary();
        $this->assertSame(3, $summary['kpis']['total_schools']);
        $this->assertSame(1, $summary['kpis']['public_schools']);
        $this->assertSame(2, $summary['kpis']['private_schools']);
        $this->assertSame(24, $summary['kpis']['districts']);
        $this->assertSame(2, $summary['kpis']['districts_with_schools']);
        $this->assertSame(1, $summary['levels']['SD']);
        $this->assertSame(1, $summary['other_levels']);
    }

    public function test_district_summary_uses_active_dataset_with_canonical_identifiers_and_level_breakdown(): void
    {
        $active = Dataset::factory()->active()->create();
        $inactive = Dataset::factory()->create(['is_active' => false]);
        School::factory()->for($active)->create(['education_level' => 'SD', 'status' => 'Negeri', 'district' => 'Bintuni']);
        School::factory()->for($active)->create(['education_level' => 'TK', 'status' => 'Swasta', 'district' => 'Bintuni']);
        School::factory()->for($active)->create(['education_level' => 'SMP', 'status' => 'Negeri', 'district' => 'Merdey']);
        School::factory()->for($inactive)->create(['education_level' => 'SD', 'status' => 'Negeri', 'district' => 'Tidak Aktif']);

        $summary = app(TifaDataService::class)->homepageDistrictSummary();

        $this->assertTrue($summary['available']);
        $this->assertSame(0, $summary['null_or_empty_districts']);
        $this->assertSame(['Bintuni', 'Merdey'], array_column($summary['districts'], 'identifier'));
        $this->assertSame(2, $summary['districts'][0]['total_schools']);
        $this->assertSame(1, $summary['districts'][0]['public_schools']);
        $this->assertSame(1, $summary['districts'][0]['private_schools']);
        $this->assertSame(1, $summary['districts'][0]['levels']['SD']);
        $this->assertSame(1, $summary['districts'][0]['levels']['TK']);
        $this->assertSame(1, $summary['districts'][1]['levels']['SMP']);
    }

    public function test_homepage_includes_the_map_container_without_requiring_boundary_data(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('tifaEducationMap(', false)
            ->assertSee('tifa-district-summary')
            ->assertSee('Peta distrik Kabupaten Teluk Bintuni')
            ->assertSee('tifaDistrictSummary(', false);
    }

    public function test_verified_boundary_snapshot_contains_all_administrative_districts(): void
    {
        $snapshot = json_decode(File::get(resource_path('geojson/teluk-bintuni-districts.big.geojson')), true, flags: JSON_THROW_ON_ERROR);
        $districts = collect($snapshot['features'])->pluck('properties.WADMKC');

        $this->assertCount(24, $districts);
        $this->assertContains('Biscoop', $districts);
        $this->assertContains('Aranday', $districts);
    }
}
