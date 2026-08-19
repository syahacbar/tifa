<?php

namespace App\Services;

use App\Models\PublicDocument;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class PublicDocumentService
{
    /**
     * Get active public documents for homepage (default up to 6 latest).
     *
     * @return array<int, array{
     *     id: int,
     *     title: string,
     *     file: string,
     *     file_url: string,
     *     download_name: string,
     *     thumbnail: string|null,
     *     thumbnail_url: string|null,
     *     published_at: string|null
     * }>
     */
    public function getHomepageDocuments(int $limit = 6): array
    {
        if (Schema::hasTable('public_documents')) {
            // If the database has records, it is the sole source of truth
            if (PublicDocument::query()->exists()) {
                $documents = PublicDocument::active()
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get();

                return $documents->map(fn (PublicDocument $doc) => $this->formatDocument($doc))->values()->all();
            }
        }

        // Graceful fallback for Renstra legacy ONLY if database has no records yet / table is missing
        if (file_exists(public_path('documents/renstra-dinas-pendidikan-teluk-bintuni.pdf'))) {
            return [
                [
                    'id' => 1,
                    'title' => 'Rencana Strategis (Renstra) Dinas Pendidikan Kabupaten Teluk Bintuni',
                    'file' => '/documents/renstra-dinas-pendidikan-teluk-bintuni.pdf',
                    'file_url' => '/documents/renstra-dinas-pendidikan-teluk-bintuni.pdf',
                    'download_name' => 'renstra-dinas-pendidikan-teluk-bintuni.pdf',
                    'thumbnail' => null,
                    'thumbnail_url' => null,
                    'published_at' => '2021-01-01',
                ],
            ];
        }

        return [];
    }

    /**
     * Get paginated active public documents.
     */
    public function getPaginatedDocuments(int $perPage = 6): LengthAwarePaginator
    {
        return PublicDocument::active()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Format a PublicDocument model into array for public output.
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     file: string,
     *     file_url: string,
     *     download_name: string,
     *     thumbnail: string|null,
     *     thumbnail_url: string|null,
     *     published_at: string|null
     * }
     */
    public function formatDocument(PublicDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'title' => $doc->title,
            'file' => $doc->file_url,
            'file_url' => $doc->file_url,
            'download_name' => $doc->download_name,
            'thumbnail' => $doc->thumbnail_url,
            'thumbnail_url' => $doc->thumbnail_url,
            'published_at' => $doc->published_at?->format('Y-m-d'),
        ];
    }
}
