<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fails_when_there_is_no_active_dataset(): void
    {
        $this->artisan('tifa:data-check')
            ->expectsOutputToContain('Tidak ada dataset aktif.')
            ->assertFailed();
    }

    public function test_command_displays_summary_for_the_active_dataset_without_changing_data(): void
    {
        $dataset = Dataset::factory()->active()->create(['name' => 'Dataset Pengujian']);
        School::factory()->for($dataset)->create([
            'npsn' => '60725746',
            'education_level' => 'SD',
            'status' => 'Negeri',
            'district' => 'Bintuni',
            'students_male' => 10,
            'students_female' => 12,
            'students_total' => 22,
            'teachers' => 3,
        ]);
        School::factory()->for($dataset)->create([
            'npsn' => '60725746',
            'name' => 'Sekolah Kedua',
            'education_level' => 'SMP',
            'status' => 'Negeri',
            'district' => 'Tuhiba',
        ]);

        $this->artisan('tifa:data-check')
            ->expectsOutputToContain('Dataset Pengujian')
            ->expectsOutputToContain('Statistik utama')
            ->expectsOutputToContain('60725746 (2 sekolah)')
            ->expectsOutputToContain('Ringkasan kualitas')
            ->assertSuccessful();

        $this->assertDatabaseCount('datasets', 1);
        $this->assertDatabaseCount('schools', 2);
    }
}
