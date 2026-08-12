<?php

namespace App\Services;

class TifaGreetingService
{
    /**
     * Only accepts a complete greeting. A greeting followed by a substantive
     * question must continue to the normal deterministic data routing.
     */
    public function respondsTo(string $question): bool
    {
        $greeting = $this->normalized($question);

        return preg_match('/^(?:halo|hai|pagi|selamat (?:pagi|siang|sore|malam)|assalamu?alaikum|assalamu alaikum)(?: tifaa)?$/u', $greeting) === 1;
    }

    /** @return array<string, mixed> */
    public function response(string $question): array
    {
        $opening = match (true) {
            str_starts_with($this->normalized($question), 'assalamu') => 'Waalaikumsalam',
            str_starts_with($this->normalized($question), 'hai') => 'Hai',
            default => 'Halo',
        };
        $timeGreeting = $this->timeGreeting();

        return [
            'question' => $question,
            'intent' => ['type' => 'greeting'],
            'answer' => "{$opening}, {$timeGreeting}! Saya TIFAA, Tata Kelola dan Informasi Pendidikan Terintegrasi Kabupaten Teluk Bintuni. Saya dapat membantu memberikan informasi seputar pendidikan, seperti sekolah, guru, siswa dan data pendidikan lainnya. Apa yang ingin Anda ketahui?",
            'data' => null,
            'visualization' => null,
            'source' => null,
        ];
    }

    private function timeGreeting(): string
    {
        $hour = now(config('app.timezone'))->hour;

        return match (true) {
            $hour >= 5 && $hour < 11 => 'selamat pagi',
            $hour >= 11 && $hour < 15 => 'selamat siang',
            $hour >= 15 && $hour < 18 => 'selamat sore',
            default => 'selamat malam',
        };
    }

    private function normalized(string $question): string
    {
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($question)) ?? '');
    }
}
