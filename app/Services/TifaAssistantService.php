<?php

namespace App\Services;

class TifaAssistantService
{
    public function __construct(
        private TifaIntentService $intentService,
        private TifaDataService $dataService,
        private TifaResponseFormatter $formatter,
        private TeacherAnalyticsIntentService $teacherIntent,
        private TeacherAnalyticsService $teacherAnalytics,
        private TifaPrivacyGuard $privacyGuard,
        private GeneralTeacherConversationService $generalTeacher,
        private OfficialTerminologyService $terminology,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ask(string $question): array
    {
        if ($definition = $this->terminology->directDefinition($question)) {
            return ['question' => $question, 'intent' => ['type' => 'official_terminology'], 'answer' => $definition, 'data' => null, 'visualization' => null, 'source' => null];
        }
        if ($this->privacyGuard->blocks($question)) return $this->privacyGuard->response($question);
        $teacher = $this->teacherIntent->parse($question);
        if ($teacher !== null) {
            if (isset($teacher['blocked'])) throw new \App\Exceptions\TifaIntentException('TIFA saat ini hanya menyediakan statistik agregat guru, bukan data pribadi individual.');
            $data = $this->teacherAnalytics->query($teacher);
            return ['question' => $question, 'intent' => ['type' => 'teacher_analytics', ...$teacher], 'answer' => $this->formatter->formatTeacher($data), 'data' => isset($data['value']) ? ['value' => $data['value']] : $data['data'], 'visualization' => $data['visualization'], 'source' => ['reference_period' => $data['batch']['source_period'], 'authoritative' => true]];
        }
        if ($this->generalTeacher->handles($question)) {
            return ['question' => $question, 'intent' => ['type' => 'general_conversation'], 'answer' => $this->generalTeacher->answer($question), 'data' => null, 'visualization' => null, 'source' => null];
        }
        $intent = $this->intentService->parse($question);
        $data = $this->dataService->query($intent);
        $isKpi = $data['visualization'] === 'kpi';

        return [
            'question' => $question,
            'intent' => $intent,
            'answer' => $isKpi ? $this->formatter->format($intent, $data) : $this->formatter->formatAnalytic($intent, $data),
            'data' => $isKpi ? ['value' => $data['value']] : $data['data'],
            'visualization' => $data['visualization'],
            'source' => [
                'dataset' => $data['dataset']['name'],
                'reference_period' => $data['dataset']['reference_period'],
                'source_date' => $data['source_date'],
            ],
        ];
    }
}
