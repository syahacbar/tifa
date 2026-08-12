<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAssignmentSchoolReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<TeacherAssignment, $this> */
    public function assignment(): BelongsTo { return $this->belongsTo(TeacherAssignment::class, 'teacher_assignment_id'); }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
}
