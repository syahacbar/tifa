<?php

namespace App\Services;

class TifaAssistantService
{
    public function __construct(
        private TifaIntentService $intentService,
        private TifaLocalDataIntentService $localDataIntent,
        private TifaDataService $dataService,
        private TifaResponseFormatter $formatter,
        private TeacherAnalyticsIntentService $teacherIntent,
        private TeacherAnalyticsService $teacherAnalytics,
        private TeacherDataTool $teacherTool,
        private TeacherAnalyticsContextService $teacherContext,
        private TifaPrivacyGuard $privacyGuard,
        private GeneralTeacherConversationService $generalTeacher,
        private OfficialTerminologyService $terminology,
        private TifaGreetingService $greeting,
        private TifaPresentationService $presentation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ask(string $question, ?array $context = null): array
    {
        if ($this->greeting->respondsTo($question)) return $this->greeting->response($question);
        if ($definition = $this->terminology->directDefinition($question)) {
            return ['question' => $question, 'intent' => ['type' => 'official_terminology'], 'answer' => $definition, 'data' => null, 'visualization' => null, 'source' => null];
        }
        if ($this->privacyGuard->blocks($question)) return $this->privacyGuard->response($question);
        if ($this->teacherIntent->shouldPrioritize($question, $context) && ($teacher = $this->teacherIntent->parseSemantic($question, $context)) !== null) {
            return $this->teacherDataResponse($question, $teacher);
        }
        if ($intent = $this->localDataIntent->parse($question)) {
            return $this->schoolDataResponse($question, $intent);
        }
        $teacher = $this->teacherIntent->parseSemantic($question, $context);
        if ($teacher !== null) {
            return $this->teacherDataResponse($question, $teacher);
        }
        if ($this->generalTeacher->handles($question)) {
            return ['question' => $question, 'intent' => ['type' => 'general_conversation'], 'answer' => $this->generalTeacher->answer($question), 'data' => null, 'visualization' => null, 'source' => null];
        }
        $intent = $this->intentService->parse($question);
        return $this->schoolDataResponse($question, $intent);
    }

    private function teacherDataResponse(string $question, \App\Queries\SemanticQuery $teacher): array
    {
        $teacherContract = app(TeacherSemanticQueryAdapter::class)->toToolContract($teacher);
        $tool = $this->teacherTool->execute($teacherContract);
        $data = $tool['presentation'];
        $data['quality'] = $tool['quality'];
        $data['top_n'] = $teacherContract['top_n'];
        $data['comparison'] = $teacherContract['comparison'];
        $data['unavailable_categories'] = app(TeacherEmploymentStatusCatalog::class)->unavailableRequestedCategories($question);

        return ['question' => $question, 'intent' => ['type' => 'teacher_analytics', ...$teacherContract], 'answer' => $this->formatter->formatTeacher($data), 'data' => $tool['data'], 'presentation' => $this->presentation->forTeacher($data), 'visualization' => $data['visualization'], 'source' => ['reference_period' => $tool['provenance']['reference_period'], 'authoritative' => true], 'teacher_context' => $this->teacherContext->fromIntent($teacherContract)];
    }

    /** @param array{action: string, filters: array{education_level: ?string, status: ?string, district: ?string}} $intent
     * @return array<string, mixed>
     */
    private function schoolDataResponse(string $question, array $intent): array
    {
        $data = $this->dataService->query($intent);
        $isKpi = $data['visualization'] === 'kpi';

        return [
            'question' => $question,
            'intent' => $intent,
            'answer' => $isKpi ? $this->formatter->format($intent, $data) : $this->formatter->formatAnalytic($intent, $data),
            'data' => $isKpi ? ['value' => $data['value']] : $data['data'],
            'presentation' => $this->presentation->forSchool($intent, $data),
            'visualization' => $data['visualization'],
            'source' => [
                'dataset' => $data['dataset']['name'],
                'reference_period' => $data['dataset']['reference_period'],
                'source_date' => $data['source_date'],
            ],
        ];
    }
}
