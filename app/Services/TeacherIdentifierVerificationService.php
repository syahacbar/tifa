<?php

namespace App\Services;

use App\Models\TeacherImportBatch;
use Illuminate\Support\Collection;

class TeacherIdentifierVerificationService
{
    /** @return Collection<int, array<string, mixed>> */
    public function verify(TeacherImportBatch $batch, TeacherImportReviewService $reviews): Collection
    {
        return $reviews->duplicateGroups($batch)->map(function (array $group) use ($batch): array {
            $assignments = $batch->assignments()->where('deduplication_fingerprint', $group['fingerprint'])->get();
            $statuses = [
                'NIK_MATCH' => $this->status($assignments->pluck('nik')->all()),
                'NIP_MATCH' => $this->status($assignments->pluck('nip')->all()),
                'NUPTK_MATCH' => $this->status($assignments->pluck('nuptk')->all()),
                'NAME_MATCH' => $this->status($assignments->pluck('full_name')->all()),
                'BIRTH_DATA_MATCH' => $this->status($assignments->map(fn ($item) => ($item->birth_place ?: '').'|'.($item->birth_date?->format('Y-m-d') ?: ''))->all(), true),
            ];
            $npsns = $assignments->pluck('source_npsn')->filter()->unique();
            $recommendation = in_array('different', array_slice($statuses, 0, 3), true) ? 'distinct_person'
                : (in_array('unavailable', $statuses, true) ? 'requires_manual_review'
                    : ($npsns->count() > 1 ? 'same_person_multiple_assignments' : 'true_duplicate'));
            return $group + ['identifier_statuses' => $statuses, 'final_recommendation' => $recommendation];
        })->values();
    }

    /** @param array<int, mixed> $values */
    private function status(array $values, bool $compound = false): string
    {
        $available = array_values(array_filter($values, fn ($value) => $compound ? $value !== '|' : $value !== null && $value !== ''));
        if ($available === []) return 'unavailable';
        if (count(array_unique($available, SORT_REGULAR)) > 1) return 'different';
        return count($available) === count($values) ? 'match' : 'unavailable';
    }
}
