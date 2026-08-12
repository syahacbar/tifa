<?php

namespace App\Services;

use App\Contracts\LlmProvider;
use App\Exceptions\LlmProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GroqLlmProvider implements LlmProvider
{
    public function chat(array $messages, array $options = []): string
    {
        if ($this->apiKey() === '') throw new LlmProviderException('GROQ_API_KEY belum dikonfigurasi.');

        try {
            $response = $this->http()->post('/chat/completions', [
                'model' => $this->model(),
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? 0,
                'max_tokens' => $options['max_tokens'] ?? 256,
                ...(($options['json'] ?? false) ? ['response_format' => ['type' => 'json_object']] : []),
            ]);
        } catch (ConnectionException $exception) {
            throw new LlmProviderException('Koneksi ke Groq gagal.', previous: $exception);
        }

        if (! $response->successful()) {
            $type = $response->status() === 429 ? 'rate limited' : 'HTTP '.$response->status();
            $detail = $response->json('error.message');
            throw new LlmProviderException('Groq gagal merespons ('.$type.').'.(is_string($detail) ? ' '.$detail : ''));
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') throw new LlmProviderException('Respons Groq tidak memuat choices[0].message.content yang valid.');

        return trim($content);
    }

    public function health(): array
    {
        try {
            $this->chat([
                ['role' => 'system', 'content' => 'Reply with OK only.'],
                ['role' => 'user', 'content' => 'health check'],
            ], ['max_tokens' => 4]);
            return ['healthy' => true, 'provider' => $this->providerName(), 'model' => $this->model(), 'error' => null];
        } catch (LlmProviderException $exception) {
            return ['healthy' => false, 'provider' => $this->providerName(), 'model' => $this->model(), 'error' => $exception->getMessage()];
        }
    }

    public function providerName(): string { return 'groq'; }

    public function model(): string { return (string) config('services.tifa_ai.groq.model'); }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.tifa_ai.groq.base_url'), '/'))
            ->withToken($this->apiKey())
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.tifa_ai.groq.timeout', 15))
            ->timeout((int) config('services.tifa_ai.groq.timeout', 15));
    }

    private function apiKey(): string { return (string) config('services.tifa_ai.groq.api_key'); }
}
