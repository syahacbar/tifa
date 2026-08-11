<?php

namespace Database\Factories;

use App\Models\Dataset;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<School> */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $male = fake()->numberBetween(0, 500);
        $female = fake()->numberBetween(0, 500);

        return [
            'dataset_id' => Dataset::factory(),
            'npsn' => fake()->unique()->numerify('########'),
            'name' => fake()->company(),
            'education_level' => fake()->randomElement(['PAUD', 'SD', 'SMP', 'SMA', 'SMK']),
            'district' => fake()->city(),
            'status' => fake()->randomElement(['Negeri', 'Swasta']),
            'students_male' => $male,
            'students_female' => $female,
            'students_total' => $male + $female,
            'study_groups' => fake()->numberBetween(1, 30),
            'teachers' => fake()->numberBetween(1, 80),
            'education_staff' => fake()->numberBetween(0, 30),
            'classrooms' => fake()->numberBetween(1, 40),
            'laboratories' => fake()->numberBetween(0, 10),
            'libraries' => fake()->numberBetween(0, 3),
        ];
    }
}
