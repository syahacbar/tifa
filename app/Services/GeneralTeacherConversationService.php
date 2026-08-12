<?php

namespace App\Services;

class GeneralTeacherConversationService
{
    public function __construct(private OllamaClient $ollama, private OfficialTerminologyService $terminology) {}

    public function handles(string $question): bool
    {
        $text = mb_strtolower($question);
        return preg_match('/\b(apa|bagaimana|mengapa)\b/u', $text) === 1
            && preg_match('/\b(guru|pns|pppk|kepala sekolah)\b/u', $text) === 1;
    }

    public function answer(string $question): string
    {
        if ($direct = $this->terminology->directDefinition($question)) return $direct;
        $glossary = $this->terminology->promptContext($question);
        return $this->ollama->generateText("Anda adalah TIFAA (Tata Kelola dan Informasi Pendidikan Terintegrasi). Jawab singkat, akurat, dan ramah dalam bahasa Indonesia untuk pertanyaan layanan pendidikan berikut. Jika perlu menyebut nama layanan, gunakan TIFAA. Jangan membuat statistik atau menyebut data pribadi. Jangan mengarang kepanjangan singkatan; gunakan glossary resmi ini bila relevan: {$glossary}. Jika istilah resmi tidak tersedia atau tidak yakin, jangan membuat kepanjangan sendiri. Pertanyaan: {$question}");
    }
}
