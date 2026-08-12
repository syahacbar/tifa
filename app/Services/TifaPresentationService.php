<?php

namespace App\Services;

/** Builds UI-ready presentation metadata from authoritative structured results. */
class TifaPresentationService
{
    /** @param array<string, mixed> $intent
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function forSchool(array $intent, array $data): array
    {
        $action = $intent['action'];
        if (($data['visualization'] ?? null) === 'kpi') {
            return ['type' => 'kpi', 'title' => $this->schoolTitle($action), 'value' => (int) $data['value'], 'unit' => $this->schoolUnit($action)];
        }
        $records = $data['data']['records'] ?? [];
        if ($action === 'school_list') {
            return ['type' => 'table', 'title' => 'Daftar Sekolah', 'columns' => [
                ['key' => 'no', 'label' => 'No'], ['key' => 'name', 'label' => 'Nama Sekolah'],
                ['key' => 'education_level', 'label' => 'Jenjang'],
                ['key' => 'status', 'label' => 'Status'], ['key' => 'district', 'label' => 'Distrik'],
            ], 'rows' => array_map(fn (array $record, int $index) => ['no' => $index + 1, ...$record], $records, array_keys($records))];
        }
        if ($action === 'school_ranking') {
            $metric = $data['data']['ranking_by'] ?? 'students_total';
            return $this->chart('Sekolah Teratas', 'Sekolah', $this->rankingUnit($metric), $records);
        }
        return $this->chart(
            $action === 'status_breakdown' ? 'Perbandingan Status Sekolah' : (($data['data']['limit'] ?? null) !== null ? count($records).' Distrik dengan Sekolah Terbanyak' : 'Sebaran Sekolah per Distrik'),
            $action === 'status_breakdown' ? 'Status' : 'Distrik',
            'sekolah', $records,
        );
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function forTeacher(array $data): array
    {
        $metric = (string) $data['metric'];
        $unit = $metric === 'assignment_count' ? 'penugasan' : 'guru';
        if (array_key_exists('value', $data)) {
            return ['type' => 'kpi', 'title' => $metric === 'assignment_count' ? 'Jumlah Penugasan Guru' : 'Jumlah Guru', 'value' => (int) $data['value'], 'unit' => $unit];
        }
        $dimension = (string) ($data['group_by'] ?? '');
        $title = match ($dimension) {
            'district' => ($data['comparison'] ?? false) ? 'Perbandingan Guru per Distrik' : (($data['top_n'] ?? null) !== null ? count($data['data']['records'] ?? []).' Distrik dengan Guru Terbanyak' : 'Sebaran Guru per Distrik'),
            'employment_status' => 'Guru berdasarkan Status Kepegawaian',
            'school' => 'Guru per Sekolah',
            default => 'Sebaran Guru',
        };
        return $this->chart($title, $this->dimensionLabel($dimension), $unit, $data['data']['records'] ?? []);
    }

    /** @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function chart(string $title, string $categoryLabel, string $unit, array $records): array
    {
        return ['type' => 'bar_chart', 'title' => $title, 'category_label' => $categoryLabel, 'value_label' => ucfirst($unit), 'unit' => $unit, 'data' => array_map(fn (array $row) => ['label' => (string) $row['label'], 'value' => (int) $row['value']], $records)];
    }

    private function schoolTitle(string $action): string
    {
        return ['school_count' => 'Jumlah Sekolah', 'student_total' => 'Jumlah Siswa', 'student_male_total' => 'Jumlah Siswa Laki-laki', 'student_female_total' => 'Jumlah Siswa Perempuan', 'teacher_total' => 'Jumlah Guru', 'education_staff_total' => 'Tenaga Kependidikan', 'study_group_total' => 'Rombongan Belajar', 'classroom_total' => 'Ruang Kelas', 'laboratory_total' => 'Laboratorium', 'library_total' => 'Perpustakaan'][$action] ?? 'Hasil Utama';
    }

    private function schoolUnit(string $action): string
    {
        return in_array($action, ['teacher_total', 'education_staff_total'], true) ? 'orang' : (in_array($action, ['classroom_total', 'laboratory_total', 'library_total', 'study_group_total'], true) ? 'unit' : ($action === 'student_total' ? 'siswa' : 'sekolah'));
    }

    private function rankingUnit(string $metric): string
    {
        return ['students_total' => 'siswa', 'teachers' => 'guru', 'classrooms' => 'ruang', 'laboratories' => 'laboratorium', 'libraries' => 'perpustakaan'][$metric] ?? 'nilai';
    }

    private function dimensionLabel(string $dimension): string
    {
        return ['district' => 'Distrik', 'employment_status' => 'Status', 'school' => 'Sekolah', 'education_level' => 'Jenjang'][$dimension] ?? 'Kategori';
    }
}
