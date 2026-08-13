<?php

namespace App\Services;

use App\Models\School;
use App\Models\TeacherAssignment;
use App\Queries\SemanticQuery;
use App\Exceptions\SemanticQueryValidationException;

class TeacherAnalyticsIntentService
{
    /**
     * True only for teacher questions that need analytical grouping/ranking.
     * Plain aggregate wording remains on the existing local-school contract.
     */
    public function shouldPrioritize(string $question, ?array $context = null): bool
    {
        $text = mb_strtolower(trim($question));
        if ($context && app(TeacherAnalyticsContextService::class)->normalize($context)) return true;
        if (! $this->hasTeacherTerm($text)) return false;

        return preg_match('/\b(?:terbanyak|paling banyak|paling sedikit|tersedikit|tertinggi|terbesar|terendah|terkecil|top|peringkat|ranking|sekolah\s+mana|distrik\s+mana|daerah\s+mana|berdasarkan\s+(?:sekolah|distrik)|(?:\d+|satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\s+(?:sekolah|distrik|sd|smp|sma|smk))\b/u', $text) === 1;
    }

    /** @return array<string, mixed>|null */
    public function parse(string $question, ?array $context = null): ?array
    {
        $semantic = $this->parseSemantic($question, $context);

        return $semantic === null ? null : app(TeacherSemanticQueryAdapter::class)->toToolContract($semantic);
    }

    public function parseSemantic(string $question, ?array $context = null): ?SemanticQuery
    {
        $text = mb_strtolower(trim($question));
        if (preg_match('/\b(mulai lagi|pertanyaan baru)\b/u', $text)) return null;
        $context = app(TeacherAnalyticsContextService::class)->normalize($context);
        if ($context && $this->isFollowUp($text)) return $this->semanticFromLegacy($this->followUp($text, $context));
        if (! preg_match('/\b(?:berapa|jumlah|sebaran|bandingkan|tampilkan|mana|sebutkan|terbanyak|top|peringkat|ranking)\b|\bpaling\s+(?:banyak|sedikit)\b/u', $text) || ! $this->hasTeacherTerm($text)) return null;
        if (preg_match('/\b(nik|nip|nuptk|nomor hp|telepon|tanggal lahir|alamat)\b/u', $text)) return ['blocked' => 'privacy'];
        $filters = array_fill_keys(['education_level','district','school_id','employment_status','ptk_type','ptk_position','education'], null);
        foreach (['SD','SMP','SMA','SMK','PAUD','SKB'] as $level) if (preg_match('/\b'.mb_strtolower($level).'\b/u', $text)) $filters['education_level'] = $level;
        if (app(TeacherEmploymentStatusCatalog::class)->hasPppkTerm($text)) $filters['employment_status'] = str_contains($text, 'tahap ii') ? 'PPPK Tahap II' : 'PPPK';
        elseif (preg_match('/\bpns\b/u', $text)) $filters['employment_status'] = 'PNS';
        if (str_contains($text, 'kepala sekolah')) $filters['ptk_type'] = 'Kepala Sekolah';
        if (preg_match('/\b(s1|s2|d1|d2|d3|d4)\b/u', $text, $match)) $filters['education'] = mb_strtoupper($match[1]);
        if (preg_match('/\bdi\s+distrik\s+([\pL\s]+?)(?:\?|$|\b(guru|ptk|dengan)\b)/u', $text, $match)) $filters['district'] = $this->districtFilter($match[1]);
        elseif (preg_match('/\bdi\s+([\pL]+)(?:\?|$)/u', $text, $match) && $this->district($match[1])) $filters['district'] = $match[1];
        $school = $this->school($text);
        if ($school) { $filters['school_id'] = $school->id; $filters['education_level'] = null; }
        $rankingLimit = $this->rankingLimit($text);
        $isRanking = $rankingLimit !== null || preg_match('/\b(?:terbanyak|paling banyak|paling sedikit|tersedikit|tertinggi|terbesar|terendah|terkecil|peringkat|ranking)\b/u', $text) === 1;
        $isComparison = preg_match('/\b(?:bandingkan|perbandingan)\b/u', $text) === 1
            || count(app(TeacherEmploymentStatusCatalog::class)->comparisonCategories($text)) >= 2;
        $comparisonValues = null;
        $group = null;
        if (preg_match('/\b(?:top\s+)?(?:\d+|satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\s+distrik\b/u', $text) || preg_match('/\b(?:distrik|daerah)\s+mana\b/u', $text)) $group = 'district';
        elseif ($isRanking && preg_match('/\b(?:sekolah|sd|smp|sma|smk)\b/u', $text) && ! preg_match('/\b(?:distrik|daerah)\s+mana\b/u', $text)) $group = 'school';
        elseif (preg_match('/(per|berdasarkan)\s+distrik/u', $text) || (str_contains($text, 'distrik') && (str_contains($text, 'terbanyak') || str_contains($text, 'paling banyak')))) $group = 'district';
        elseif ($isComparison && (preg_match('/\bpns\b/u', $text) || app(TeacherEmploymentStatusCatalog::class)->hasPppkTerm($text))) { $group = 'employment_status'; $filters['employment_status'] = null; }
        elseif ($isComparison) {
            $comparisonValues = $this->comparisonValues($text, 'district');
            if (count($comparisonValues) < 2) throw new SemanticQueryValidationException('Comparison membutuhkan dua distrik yang dapat dikenali.');
            $group = 'district';
        }
        elseif (preg_match('/(?:per|berdasarkan|setiap)\s+jenjang(?:\s+pendidikan)?/u', $text)) $group = 'education_level';
        elseif (preg_match('/(?:per|berdasarkan)\s+status\s+kepegawaian/u', $text)) $group = 'employment_status';
        elseif (preg_match('/(per|berdasarkan)\s+jenis ptk/u', $text)) $group = 'ptk_type';
        elseif (preg_match('/(per|berdasarkan)\s+sekolah/u', $text) || ($isRanking && (str_contains($text, 'sekolah') || $filters['education_level'] !== null))) $group = 'school';
        $top = $group && $isRanking ? ($rankingLimit ?? 1) : null;
        $metric = preg_match('/\b(?:penugasan(?:\s+guru)?|penempatan\s+guru|assignment|record nominatif)\b/u', $text) ? 'assignment_count' : 'unique_teacher_count';
        $comparison = $group !== null && $isComparison;
        $operation = $comparison ? 'comparison' : ($group ? ($top ? 'ranking' : 'breakdown') : 'count');
        $comparisonValues = $comparison ? ($comparisonValues ?? $this->comparisonValues($text, $group)) : null;
        $sort = $operation === 'ranking' ? ['field' => 'value', 'direction' => $this->sortDirection($text)] : null;
        $query = new SemanticQuery('teacher', $operation, $metric, $group, $filters, $sort, $top, $comparisonValues);
        app(SemanticQueryValidator::class)->validate($query);

        return $query;
    }

    /** @param array<string, mixed> $legacy */
    private function semanticFromLegacy(array $legacy): SemanticQuery
    {
        $operation = $legacy['operation'];
        $query = new SemanticQuery('teacher', $operation, $legacy['metric'], $legacy['group_by'], $legacy['filters'], $operation === 'ranking' ? ['field' => 'value', 'direction' => 'desc'] : null, $legacy['top_n']);
        app(SemanticQueryValidator::class)->validate($query);

        return $query;
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
        if (app(TeacherEmploymentStatusCatalog::class)->hasPppkTerm($text)) $filters['employment_status'] = 'PPPK'; elseif (preg_match('/\bpns\b/u', $text)) $filters['employment_status'] = 'PNS';
        if (preg_match('/\bdi\s+(?:distrik\s+)?([\pL\s]+?)(?:\?|$)/u', $text, $match)) $filters['district'] = trim($match[1]);
        $top = preg_match('/\b(lima|5)\s+(terbesar|terbanyak)/u', $text) ? 5 : ((str_contains($text, 'terbesar') || str_contains($text, 'terbanyak')) ? 1 : $context['top_n']);
        $group = $context['group_by']; if ($top && $group === null) $group = 'district';
        $metric = $context['metric'];
        return ['version' => 'v1', 'operation' => $group ? ($top ? 'ranking' : 'breakdown') : 'count', 'entity' => $metric === 'assignment_count' ? 'teacher_assignment' : 'teacher_identity', 'metric' => $metric, 'filters' => $filters, 'group_by' => $group, 'top_n' => $top, 'confidence' => 'contextual'];
    }

    private function isFollowUp(string $text): bool
    {
        return preg_match('/^(?:kalau|yang|lima|5|di\b|terbesar\b|terbanyak\b|paling\s+banyak\b)/u', $text) === 1;
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

    private function districtFilter(string $value): ?string
    {
        $value = trim($value);
        if (in_array(mb_strtolower($value), ['mana', 'apa', 'berapa', 'siapa'], true)) return null;

        return $value === '' ? null : $value;
    }

    private function sortDirection(string $text): string
    {
        return preg_match('/\b(?:tersedikit|paling sedikit|terendah|terkecil)\b/u', $text) === 1 ? 'asc' : 'desc';
    }

    /** @return array<int, string> */
    private function comparisonValues(string $text, ?string $group): array
    {
        if ($group === 'employment_status') return app(TeacherEmploymentStatusCatalog::class)->comparisonCategories($text);
        if ($group === 'district') return TeacherAssignment::query()->select('district')->distinct()->pluck('district')
            ->filter(fn (?string $district) => is_string($district) && $district !== '' && str_contains($text, mb_strtolower($district)))->values()->all();

        return [];
    }

    private function mentionedDistrictCount(string $text): int
    {
        return TeacherAssignment::query()->select('district')->distinct()->pluck('district')
            ->filter(fn (?string $district) => is_string($district) && $district !== '' && str_contains($text, mb_strtolower($district)))
            ->count();
    }

    private function hasTeacherTerm(string $text): bool
    {
        return preg_match('/\b(?:guru(?:nya)?|ptk|kepala sekolah|penugasan|assignment|nominatif|tenaga\s+pengajar(?:nya)?|para\s+pengajar|pengajar(?:nya)?|pendidik(?:nya)?|tenaga\s+pendidik(?:nya)?)\b/u', $text) === 1;
    }

    private function rankingLimit(string $text): ?int
    {
        $numbers = ['satu' => 1, 'dua' => 2, 'tiga' => 3, 'empat' => 4, 'lima' => 5, 'enam' => 6, 'tujuh' => 7, 'delapan' => 8, 'sembilan' => 9, 'sepuluh' => 10];
        if (! preg_match('/\b(?:top\s+)?(\d+|satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh)\s+(?:sekolah|distrik|sd|smp|sma|smk)\b/u', $text, $match)) return null;
        $value = ctype_digit($match[1]) ? (int) $match[1] : $numbers[$match[1]];

        return min(20, max(1, $value));
    }
}
