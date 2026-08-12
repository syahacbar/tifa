<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherAssignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted', 'nip' => 'encrypted', 'nuptk' => 'encrypted', 'phone' => 'encrypted',
            'birth_date' => 'date', 'is_duplicate_candidate' => 'boolean', 'source_payload' => 'array',
        ];
    }

    /** @return BelongsTo<TeacherImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(TeacherImportBatch::class, 'teacher_import_batch_id');
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return HasMany<TeacherAssignmentSchoolReview, $this> */
    public function schoolReviews(): HasMany
    {
        return $this->hasMany(TeacherAssignmentSchoolReview::class);
    }
}
