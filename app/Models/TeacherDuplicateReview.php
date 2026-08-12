<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherDuplicateReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<TeacherImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(TeacherImportBatch::class, 'teacher_import_batch_id');
    }
}
