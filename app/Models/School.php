<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dataset_id',
    'npsn',
    'source_key',
    'name',
    'education_level',
    'district',
    'status',
    'students_male',
    'students_female',
    'students_total',
    'study_groups',
    'teachers',
    'education_staff',
    'classrooms',
    'laboratories',
    'libraries',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    public static function sourceKeyFor(string $educationLevel, string $name, string $district): string
    {
        $parts = array_map(
            fn (string $value) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? '')),
            [$educationLevel, $name, $district],
        );

        return hash('sha256', implode('|', $parts));
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /** @param Builder<School> $query */
    public function scopeByEducationLevel(Builder $query, ?string $educationLevel): Builder
    {
        return $this->whereNormalized($query, 'education_level', $educationLevel);
    }

    /** @param Builder<School> $query */
    public function scopeByDistrict(Builder $query, ?string $district): Builder
    {
        return $this->whereNormalized($query, 'district', $district);
    }

    /** @param Builder<School> $query */
    public function scopeByStatus(Builder $query, ?string $status): Builder
    {
        return $this->whereNormalized($query, 'status', $status);
    }

    /** @param Builder<School> $query */
    public function scopeFromActiveDataset(Builder $query): Builder
    {
        return $query->whereHas(
            'dataset',
            fn (Builder $datasetQuery) => $datasetQuery->active(),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'students_male' => 'integer',
            'students_female' => 'integer',
            'students_total' => 'integer',
            'study_groups' => 'integer',
            'teachers' => 'integer',
            'education_staff' => 'integer',
            'classrooms' => 'integer',
            'laboratories' => 'integer',
            'libraries' => 'integer',
        ];
    }

    /** @param Builder<School> $query */
    private function whereNormalized(Builder $query, string $column, ?string $value): Builder
    {
        $normalized = mb_strtolower(trim($value ?? ''));

        if ($normalized === '') {
            return $query;
        }

        return $query->whereRaw("LOWER(TRIM({$column})) = ?", [$normalized]);
    }

    protected static function booted(): void
    {
        static::saving(function (School $school): void {
            $school->source_key = self::sourceKeyFor(
                (string) $school->education_level,
                (string) $school->name,
                (string) $school->district,
            );
        });
    }
}
