<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PublicDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'file_path',
        'thumbnail_path',
        'is_active',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'date',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getFileUrlAttribute(): string
    {
        if (empty($this->file_path)) {
            return '';
        }

        if (str_starts_with($this->file_path, '/') || str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        if (file_exists(public_path($this->file_path))) {
            return '/' . ltrim($this->file_path, '/');
        }

        return '/storage/' . ltrim($this->file_path, '/');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (empty($this->thumbnail_path)) {
            return null;
        }

        if (str_starts_with($this->thumbnail_path, '/') || str_starts_with($this->thumbnail_path, 'http://') || str_starts_with($this->thumbnail_path, 'https://')) {
            return $this->thumbnail_path;
        }

        if (file_exists(public_path($this->thumbnail_path))) {
            return '/' . ltrim($this->thumbnail_path, '/');
        }

        return '/storage/' . ltrim($this->thumbnail_path, '/');
    }

    public function getDownloadNameAttribute(): string
    {
        return basename($this->file_path) ?: 'dokumen.pdf';
    }
}
