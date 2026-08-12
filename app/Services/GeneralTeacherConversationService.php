<?php

namespace App\Services;

use App\Contracts\LlmProvider;

class GeneralTeacherConversationService
{
    public function __construct(private LlmProvider $llm, private OfficialTerminologyService $terminology) {}

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
        return $this->llm->chat([
            ['role' => 'system', 'content' => "Anda adalah TIFAA (Tata Kelola dan Informasi Pendidikan Terintegrasi). Jawab singkat, akurat, dan ramah dalam bahasa Indonesia. Jika perlu menyebut nama layanan, gunakan TIFAA. Jangan membuat statistik atau menyebut data pribadi. Jangan mengarang kepanjangan singkatan; gunakan glossary resmi ini bila relevan: {$glossary}. Jika istilah resmi tidak tersedia atau tidak yakin, jangan membuat kepanjangan sendiri."],
            ['role' => 'user', 'content' => $question],
        ], ['temperature' => 0.2]);
    }
}
