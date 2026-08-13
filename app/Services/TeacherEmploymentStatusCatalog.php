<?php

namespace App\Services;

/** Canonical, audited conversational categories for teacher employment status. */
class TeacherEmploymentStatusCatalog
{
    /** @var array<string, array<int, string>> */
    private const SOURCE_STATUSES = [
        'PNS' => ['PNS'],
        // The source distinguishes this verified stage, but TIFAA reports it within the PPPK family.
        'PPPK' => ['PPPK', 'PPPK Tahap II'],
    ];

    /** @return array<int, string> */
    public function sourceStatusesFor(string $category): array
    {
        return self::SOURCE_STATUSES[$category] ?? [];
    }

    public function hasPppkTerm(string $text): bool
    {
        return preg_match('/\b(?:pppk|p3k)\b/u', $text) === 1;
    }

    /** @return array<int, string> */
    public function comparisonCategories(string $text): array
    {
        $categories = [];
        if (preg_match('/\bpns\b/u', $text)) $categories[] = 'PNS';
        if ($this->hasPppkTerm($text)) $categories[] = 'PPPK';

        return $categories;
    }

    /** @return array<int, string> */
    public function unavailableRequestedCategories(string $text): array
    {
        return preg_match('/\b(?:guru\s+)?kontrak\b/u', $text) === 1 ? ['Kontrak'] : [];
    }
}
