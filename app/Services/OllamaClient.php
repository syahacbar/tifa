<?php

namespace App\Services;

use App\Exceptions\OllamaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OllamaClient
{
    /** @return array{healthy: bool, base_url: string, model: string, error: ?string} */
    public function health(): array
    {
        try {
            $response = $this->http()->get('/api/tags');
        } catch (ConnectionException $exception) {
            return $this->unhealthy("Koneksi ke Ollama gagal: {$exception->getMessage()}");
        }

        if (! $response->successful()) {
            return $this->unhealthy("Ollama merespons HTTP {$response->status()}.");
        }

        return [
            'healthy' => true,
            'base_url' => $this->baseUrl(),
            'model' => $this->model(),
            'error' => null,
        ];
    }

    public function generate(string $prompt): string
    {
        try {
            $response = $this->http()->post('/api/generate', [
                'model' => $this->model(),
                'prompt' => $prompt,
                'stream' => false,
                'think' => false,
                'format' => 'json',
                'options' => [
                    'temperature' => 0,
                    'num_predict' => 256,
                ],
            ]);
        } catch (ConnectionException $exception) {
            throw new OllamaException("Koneksi ke Ollama gagal: {$exception->getMessage()}", previous: $exception);
        }

        if (! $response->successful()) {
            throw new OllamaException($this->requestError('Ollama gagal menghasilkan intent', $response->status(), $response->json('error')));
        }

        $content = $response->json('response');
        if (! is_string($content) || trim($content) === '') {
            throw new OllamaException('Respons Ollama tidak memuat field response yang valid.');
        }

        return $content;
    }

    public function generateText(string $prompt): string
    {
        try {
            $response = $this->http()->post('/api/generate', ['model' => $this->model(), 'prompt' => $prompt, 'stream' => false, 'think' => false, 'options' => ['temperature' => 0.2, 'num_predict' => 256]]);
        } catch (ConnectionException $exception) { throw new OllamaException("Koneksi ke Ollama gagal: {$exception->getMessage()}", previous: $exception); }
        if (! $response->successful()) throw new OllamaException($this->requestError('Ollama gagal menghasilkan jawaban', $response->status(), $response->json('error')));
        $content = $response->json('response');
        if (! is_string($content) || trim($content) === '') throw new OllamaException('Respons Ollama tidak memuat field response yang valid.');
        return trim($content);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->connectTimeout($this->timeout())
            ->timeout($this->timeout());
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.tifa_ai.ollama.base_url'), '/');
    }

    private function model(): string
    {
        return (string) config('services.tifa_ai.ollama.model');
    }

    private function timeout(): int
    {
        return (int) config('services.tifa_ai.ollama.timeout', 60);
    }

    /** @return array{healthy: false, base_url: string, model: string, error: string} */
    private function unhealthy(string $error): array
    {
        return [
            'healthy' => false,
            'base_url' => $this->baseUrl(),
            'model' => $this->model(),
            'error' => $error,
        ];
    }

    private function requestError(string $prefix, int $status, mixed $detail): string
    {
        $message = "{$prefix} (HTTP {$status})";

        return is_string($detail) && trim($detail) !== ''
            ? "{$message}: {$detail}"
            : "{$message}.";
    }
}
