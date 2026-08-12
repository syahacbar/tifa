<?php

namespace App\Services;

use App\Contracts\LlmProvider;

class OllamaLlmProvider implements LlmProvider
{
    public function __construct(private OllamaClient $client) {}

    public function chat(array $messages, array $options = []): string
    {
        $prompt = implode("\n\n", array_map(fn (array $message) => $message['content'], $messages));

        return ($options['json'] ?? false) ? $this->client->generate($prompt) : $this->client->generateText($prompt);
    }

    public function health(): array
    {
        return [...$this->client->health(), 'provider' => $this->providerName()];
    }

    public function providerName(): string { return 'ollama'; }

    public function model(): string { return (string) config('services.tifa_ai.ollama.model'); }
}
