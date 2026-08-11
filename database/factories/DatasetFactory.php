<?php

namespace Database\Factories;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dataset> */
class DatasetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Data Sekolah '.fake()->year(),
            'source_organization' => 'Dinas Pendidikan Kabupaten Teluk Bintuni',
            'source_url' => fake()->optional()->url(),
            'reference_period' => fake()->year(),
            'published_at' => fake()->optional()->dateTimeBetween('-1 year'),
            'description' => fake()->optional()->sentence(),
            'metadata' => ['version' => '1.0'],
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
