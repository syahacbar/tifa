<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    public const STATUS_BARU = 'baru';
    public const STATUS_DIPROSES = 'diproses';
    public const STATUS_SELESAI = 'selesai';

    public const STATUSES = [
        self::STATUS_BARU => 'Baru',
        self::STATUS_DIPROSES => 'Diproses',
        self::STATUS_SELESAI => 'Selesai',
    ];

    protected $fillable = [
        'name',
        'phone',
        'complaint_type',
        'complaint_text',
        'attachment_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_BARU => 'bg-sky-50 text-sky-800 border-sky-200',
            self::STATUS_DIPROSES => 'bg-amber-50 text-amber-800 border-amber-200',
            self::STATUS_SELESAI => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    public function hasAttachment(): bool
    {
        return !empty($this->attachment_path);
    }
}
