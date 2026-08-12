<?php

namespace App\Console\Commands;

use App\Services\TeacherAnalyticsIntentService;
use App\Services\TeacherAnalyticsService;
use App\Services\TifaResponseFormatter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:ask-teacher {question : Pertanyaan statistik guru}')] #[Description('Diagnostik read-only conversational teacher analytics')]
class AskTeacherCommand extends Command
{
    public function handle(TeacherAnalyticsIntentService $intent, TeacherAnalyticsService $analytics, TifaResponseFormatter $formatter): int
    {
        $parsed = $intent->parse($this->argument('question'));
        if (! $parsed || isset($parsed['blocked'])) { $this->error('Teacher analytics tidak terdeteksi atau ditolak oleh privacy guard.'); return self::FAILURE; }
        $data = $analytics->query($parsed);
        $this->line('Detected intent: teacher_analytics'); $this->line('Parse confidence: '.$parsed['confidence']);
        $this->line('Structured query: '.json_encode($parsed)); $this->line('Analytics result: '.json_encode(isset($data['value']) ? ['value' => $data['value']] : $data['data']));
        $this->info('Final response: '.$formatter->formatTeacher($data)); return self::SUCCESS;
    }
}
