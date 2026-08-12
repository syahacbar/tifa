<?php

namespace App\Services;

class TifaPrivacyGuard
{
    public function blocks(string $question): bool
    {
        return preg_match('/\b(nik|nip|nuptk|nomor\s*(hp|telepon)|telepon|alamat|tanggal\s+lahir)\b/ui', $question) === 1;
    }

    public function response(string $question): array
    {
        return [
            'question' => $question, 'intent' => ['type' => 'privacy_guard'],
            'answer' => 'Maaf, TIFAA tidak menampilkan data pribadi seperti NIK, NIP, NUPTK, nomor telepon, alamat, atau tanggal lahir melalui layanan percakapan. Saya dapat membantu dengan statistik atau informasi agregat data guru.',
            'data' => null, 'visualization' => null, 'source' => null,
        ];
    }
}
