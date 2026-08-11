<?php

namespace App\Models;

use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'source_organization',
    'source_url',
    'reference_period',
    'published_at',
    'description',
    'metadata',
    'is_active',
])]
class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use HasFactory;

    /** @return HasMany<School, $this> */
    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    /** @param Builder<Dataset> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function current(): ?self
    {
        return static::query()
            ->active()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
