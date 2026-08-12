<?php

namespace App\Services;

use App\Exceptions\TifaIntentException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class TifaIntentService
{
    public function __construct(private OllamaClient $ollama) {}

    /**
     * Ubah pertanyaan bahasa alami menjadi intent query TIFA yang aman.
     *
     * @return array{action: string, filters: array{education_level: ?string, status: ?string, district: ?string}}
     */
    public function parse(string $question): array
    {
        if (config('services.tifa_ai.provider') !== 'ollama') {
            throw new TifaIntentException('Provider AI TIFA yang dikonfigurasi tidak didukung.');
        }

        $rawIntent = $this->ollama->generate($this->prompt($question));

        try {
            $intent = json_decode($rawIntent, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TifaIntentException('Respons intent Ollama bukan JSON yang valid.', previous: $exception);
        }

        if (! is_array($intent) || array_is_list($intent)) {
            throw new TifaIntentException('Respons intent Ollama harus berupa object JSON.');
        }

        $unsupportedKeys = array_diff(array_keys($intent), ['action', 'filters', 'options']);
        if ($unsupportedKeys !== []) {
            throw new TifaIntentException('Respons intent Ollama memiliki field yang tidak didukung.');
        }

        try {
            $validated = Validator::make($intent, [
                'action' => ['required', 'string', Rule::in(TifaDataService::supportedActions())],
                'filters' => ['required', 'array:education_level,status,district'],
                'filters.education_level' => ['nullable', 'string'],
                'filters.status' => ['nullable', 'string'],
                'filters.district' => ['nullable', 'string'],
                'options' => ['sometimes', 'array:ranking_by,limit'],
                'options.ranking_by' => ['nullable', 'string', Rule::in(TifaDataService::rankingMetrics())],
                'options.limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw new TifaIntentException('Respons intent Ollama tidak sesuai schema TIFA.', previous: $exception);
        }

        $result = [
            'action' => $validated['action'],
            'filters' => [
                'education_level' => $this->normalizeFilter($validated['filters']['education_level'] ?? null),
                'status' => $this->normalizeFilter($validated['filters']['status'] ?? null),
                'district' => $this->normalizeDistrict($validated['filters']['district'] ?? null),
            ],
        ];

        if ($validated['action'] === 'school_ranking') {
            $result['options'] = [
                'ranking_by' => $validated['options']['ranking_by'] ?? 'students_total',
                'limit' => $validated['options']['limit'] ?? 10,
            ];
        } elseif (isset($validated['options'])) {
            throw new TifaIntentException('Opsi hanya didukung untuk ranking sekolah.');
        }

        return $result;
    }

    private function prompt(string $question): string
    {
        $actions = implode(', ', TifaDataService::supportedActions());

        return <<<PROMPT
Parser intent TIFA. Balas SATU JSON valid saja, tanpa markdown atau teks lain.
Actions: {$actions}.
Schema: {"action":"...","filters":{"education_level":null,"status":null,"district":null}}.
Filter hanya education_level, status, district; gunakan kapital. "negeri"=NEGERI, "swasta"=SWASTA. Kabupaten Teluk Bintuni bukan distrik, jadi district=null.
Selalu salin distrik bernama dari pertanyaan ke filter district; jangan menghilangkannya.
"tampilkan/daftar/list" sekolah = school_list. "terbanyak/teratas" = school_ranking dengan options {"ranking_by":"students_total|teachers|classrooms|laboratories|libraries","limit":1-20}. Sebaran distrik=district_breakdown, jenjang=education_level_breakdown, negeri vs swasta=status_breakdown. Options hanya untuk school_ranking.
Contoh tepat: "Sebaran SD berdasarkan distrik" = {"action":"district_breakdown","filters":{"education_level":"SD","status":null,"district":null}}. "Tampilkan sekolah swasta di Distrik Bintuni" = {"action":"school_list","filters":{"education_level":null,"status":"SWASTA","district":"BINTUNI"}}.
Jangan membuat SQL atau angka statistik.
Pertanyaan: {$question}
PROMPT;
    }

    private function normalizeFilter(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_strtoupper(trim($value));
    }

    private function normalizeDistrict(mixed $value): ?string
    {
        $district = $this->normalizeFilter($value);

        return $district === 'TELUK BINTUNI' ? null : $district;
    }
}
