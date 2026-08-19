<?php

namespace Database\Factories;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '0812' . fake()->numerify('########'),
            'complaint_type' => fake()->randomElement(['Pelayanan Pendidikan', 'Sarana Prasarana', 'Sekolah', 'Guru / Tenaga Kependidikan', 'Lainnya']),
            'complaint_text' => fake()->paragraph(3),
            'attachment_path' => null,
            'status' => Complaint::STATUS_BARU,
        ];
    }

    public function withAttachment(string $path = 'complaints/sample-attachment.pdf'): static
    {
        return $this->state(fn (array $attributes) => [
            'attachment_path' => $path,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Complaint::STATUS_DIPROSES,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Complaint::STATUS_SELESAI,
        ]);
    }
}
