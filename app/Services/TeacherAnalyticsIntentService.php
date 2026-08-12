<?php

namespace App\Services;

use App\Models\School;
use App\Models\TeacherAssignment;

class TeacherAnalyticsIntentService
{
    /** @return array<string, mixed>|null */
    public function parse(string $question, ?array $context = null): ?array
    {
        $text = mb_strtolower(trim($question));
        if (preg_match('/\b(mulai lagi|pertanyaan baru)\b/u', $text)) return null;
        $context = app(TeacherAnalyticsContextService::class)->normalize($context);
        if ($context && $this->isFollowUp($text)) return $this->followUp($text, $context);
        if (! preg_match('/\b(berapa|jumlah|sebaran|bandingkan|tampilkan|mana|sebutkan|terbanyak|top)\b/u', $text) || ! preg_match('/\b(guru|ptk|kepala sekolah|penugasan|assignment|nominatif)\b/u', $text)) return null;
        if (preg_match('/\b(nik|nip|nuptk|nomor hp|telepon|tanggal lahir|alamat)\b/u', $text)) return ['blocked' => 'privacy'];
        $filters = array_fill_keys(['education_level','district','school_id','employment_status','ptk_type','ptk_position','education'], null);
        foreach (['SD','SMP','SMA','SMK','PAUD','SKB'] as $level) if (preg_match('/\b'.mb_strtolower($level).'\b/u', $text)) $filters['education_level'] = $level;
        if (str_contains($text, 'pppk')) $filters['employment_status'] = str_contains($text, 'tahap ii') ? 'PPPK Tahap II' : 'PPPK';
        elseif (preg_match('/\bpns\b/u', $text)) $filters['employment_status'] = 'PNS';
        if (str_contains($text, 'kepala sekolah')) $filters['ptk_type'] = 'Kepala Sekolah';
        if (preg_match('/\b(s1|s2|d1|d2|d3|d4)\b/u', $text, $match)) $filters['education'] = mb_strtoupper($match[1]);
        if (preg_match('/\bdi\s+distrik\s+([\pL\s]+?)(?:\?|$|\b(guru|ptk|dengan)\b)/u', $text, $match)) $filters['district'] = trim($match[1]);
        elseif (preg_match('/\bdi\s+([\pL]+)(?:\?|$)/u', $text, $match) && $this->district($match[1])) $filters['district'] = $match[1];
        $school = $this->school($text);
        if ($school) { $filters['school_id'] = $school->id; $filters['education_level'] = null; }
        $group = null; $top = null;
        if (preg_match('/(per|berdasarkan)\s+distrik/u', $text) || str_contains($text, 'distrik mana') || (str_contains($text, 'distrik') && (str_contains($text, 'terbanyak') || str_contains($text, 'paling banyak')))) $group = 'district';
        elseif (str_contains($text, 'bandingkan') && (str_contains($text, 'pns') || str_contains($text, 'pppk'))) { $group = 'employment_status'; $filters['employment_status'] = null; }
        elseif (str_contains($text, 'bandingkan') && $this->mentionedDistrictCount($text) >= 2) $group = 'district';
        elseif (preg_match('/(per|berdasarkan)\s+jenjang/u', $text)) $group = 'education_level';
        elseif (preg_match('/(per|berdasarkan)\s+jenis ptk/u', $text)) $group = 'ptk_type';
        elseif (preg_match('/(per|berdasarkan)\s+sekolah/u', $text) || (str_contains($text, 'sekolah') && (str_contains($text, 'terbanyak') || str_contains($text, 'paling banyak')))) $group = 'school';
        $hasRankCount = preg_match('/\b(top|sebutkan)\s*(\d+)?/u', $text, $rankMatch) === 1;
        if ($group && (str_contains($text, 'terbanyak') || str_contains($text, 'paling banyak') || $hasRankCount)) $top = isset($rankMatch[2]) && $rankMatch[2] !== '' ? min(20, (int) $rankMatch[2]) : 1;
        $metric = preg_match('/\b(penugasan|assignment|record nominatif)\b/u', $text) ? 'assignment_count' : 'unique_teacher_count';
        return ['version' => 'v1', 'operation' => $group ? ($top ? 'ranking' : 'breakdown') : 'count', 'entity' => $metric === 'assignment_count' ? 'teacher_assignment' : 'teacher_identity', 'metric' => $metric, 'filters' => $filters, 'group_by' => $group, 'top_n' => $top, 'comparison' => $group !== null && str_contains($text, 'bandingkan'), 'confidence' => 'deterministic'];
    }

    private function school(string $text): ?School
    {
        if (! preg_match('/\bdi\s+((?:sd|smp|sma|smk)[\pL\pN\s.\-]+?)(?:\?|$)/u', $text, $match)) return null;
        $name = $this->normalize($match[1]);
        $matches = School::query()->get()->filter(fn (School $school) => $this->normalize($school->name) === $name);
        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function followUp(string $text, array $context): array
    {
        $filters = $context['filters'];
        foreach (['SD','SMP','SMA','SMK','PAUD','SKB'] as $level) if (preg_match('/\b'.mb_strtolower($level).'\b/u', $text)) $filters['education_level'] = $level;
        foreach (['sekolah dasar' => 'SD', 'sekolah menengah pertama' => 'SMP', 'sekolah menengah atas' => 'SMA'] as $alias => $level) if (str_contains($text, $alias)) $filters['education_level'] = $level;
        if (str_contains($text, 'pppk')) $filters['employment_status'] = 'PPPK'; elseif (preg_match('/\bpns\b/u', $text)) $filters['employment_status'] = 'PNS';
        if (preg_match('/\bdi\s+(?:distrik\s+)?([\pL\s]+?)(?:\?|$)/u', $text, $match)) $filters['district'] = trim($match[1]);
        $top = preg_match('/\b(lima|5)\s+(terbesar|terbanyak)/u', $text) ? 5 : ((str_contains($text, 'terbesar') || str_contains($text, 'terbanyak')) ? 1 : $context['top_n']);
        $group = $context['group_by']; if ($top && $group === null) $group = 'district';
        $metric = $context['metric'];
        return ['version' => 'v1', 'operation' => $group ? ($top ? 'ranking' : 'breakdown') : 'count', 'entity' => $metric === 'assignment_count' ? 'teacher_assignment' : 'teacher_identity', 'metric' => $metric, 'filters' => $filters, 'group_by' => $group, 'top_n' => $top, 'confidence' => 'contextual'];
    }

    private function isFollowUp(string $text): bool
    {
        return preg_match('/^(kalau|yang|lima|5|di )/u', $text) === 1 || str_contains($text, 'terbesar') || str_contains($text, 'terbanyak');
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['smpn', 'sman', 'smkn', 'sdn'], ['smp negeri', 'sma negeri', 'smk negeri', 'sd negeri'], $name);
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', $name) ?? '');
    }

    private function district(string $value): bool
    {
        return \App\Models\TeacherAssignment::query()->whereRaw('LOWER(TRIM(district)) = ?', [mb_strtolower(trim($value))])->exists();
    }

    private function mentionedDistrictCount(string $text): int
    {
        return TeacherAssignment::query()->select('district')->distinct()->pluck('district')
            ->filter(fn (?string $district) => is_string($district) && $district !== '' && str_contains($text, mb_strtolower($district)))
            ->count();
    }
}
