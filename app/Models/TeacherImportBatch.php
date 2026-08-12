<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_authoritative' => 'boolean', 'metadata' => 'array'];
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /** @return HasMany<TeacherDuplicateReview, $this> */
    public function duplicateReviews(): HasMany
    {
        return $this->hasMany(TeacherDuplicateReview::class);
    }
}
