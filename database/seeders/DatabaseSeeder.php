<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'admin@tifa.dikporabintuni.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );

        if (file_exists(public_path('documents/renstra-dinas-pendidikan-teluk-bintuni.pdf'))) {
            \App\Models\PublicDocument::firstOrCreate(
                ['file_path' => 'documents/renstra-dinas-pendidikan-teluk-bintuni.pdf'],
                [
                    'title' => 'Rencana Strategis (Renstra) Dinas Pendidikan Kabupaten Teluk Bintuni',
                    'is_active' => true,
                    'published_at' => '2021-01-01',
                ]
            );
        }
    }
}
