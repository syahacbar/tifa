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

        $defaultDocuments = [
            [
                'file_path' => 'documents/ThOjMCoynyczxVRH1hHqkA1LDPHy81HR2H7qpYkL.pdf',
                'thumbnail_path' => 'document-thumbnails/eZ4YiITsSJgv8jZHK80eE6WFHRHohbzKuPbY9zxA.png',
                'title' => 'Surat Edaran tentang Larangan Pungutan Pada Satuan Pendidikan Jenjang TK/PAUD, SD, SMP, SMA dan SMK di Kabupaten Teluk Bintuni',
                'is_active' => true,
                'published_at' => '2026-08-19',
            ],
            [
                'file_path' => 'documents/NbcJe8wWURayZfacTdZiTE64AYRLeaMgduIPUk9G.pdf',
                'thumbnail_path' => 'document-thumbnails/jmO2xGI53JMyfmQ408TXPGuaq93M6VMz7iuaT9DO.png',
                'title' => 'Rencana Strategis Tahun 2025-2029 Dinas Pendidikan, Kebudayaan, Pemuda dan Olahraga Kabupaten Teluk Bintuni',
                'is_active' => true,
                'published_at' => '2026-08-20',
            ],
            [
                'file_path' => 'documents/TmQtMilgFn0kyCqHPkV1EfgiH8RUUUYPYWFYVaBB.pdf',
                'thumbnail_path' => 'document-thumbnails/3jH6j2LrIGPcRZSRnTnwo9I717XfYke4r5XXobYt.png',
                'title' => 'Pembaruan Data Dapodik Untuk Cut Off BOSP-BOSDA 2027',
                'is_active' => true,
                'published_at' => '2026-08-20',
            ],
            [
                'file_path' => 'documents/xDm90riYXwTIbKTBtrUlIxEZgQlRpl4DZ6UvgcsS.pdf',
                'thumbnail_path' => 'document-thumbnails/KNJPOQdnYUNl3YNNeQAvtzpbE0pceqx1XZcauG22.png',
                'title' => 'Petunjuk Teknis Bantuan Operasional Pendidikan Semester-1 Tahun 2026',
                'is_active' => true,
                'published_at' => '2026-08-20',
            ],
            [
                'file_path' => 'documents/twLeK9KEdUA8wdcl3lA6m9x1PjzS6SCRHrl0Ae0W.pdf',
                'thumbnail_path' => 'document-thumbnails/H8sQiyppJVhDtOL85Fq2RUmzOuI56p0M1ysGlmgH.png',
                'title' => 'SE tentang Pengecekan Kehadiran GTK',
                'is_active' => true,
                'published_at' => '2026-08-20',
            ],
        ];

        foreach ($defaultDocuments as $docData) {
            \App\Models\PublicDocument::firstOrCreate(
                ['file_path' => $docData['file_path']],
                [
                    'thumbnail_path' => $docData['thumbnail_path'],
                    'title' => $docData['title'],
                    'is_active' => $docData['is_active'],
                    'published_at' => $docData['published_at'],
                ]
            );
        }

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
