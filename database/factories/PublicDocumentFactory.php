<?php

namespace Database\Factories;

use App\Models\PublicDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicDocument>
 */
class PublicDocumentFactory extends Factory
{
    protected $model = PublicDocument::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'file_path' => 'documents/' . fake()->slug() . '.pdf',
            'thumbnail_path' => null,
            'is_active' => true,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
