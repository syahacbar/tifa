<?php

namespace App\Contracts;

interface LlmProvider
{
    /** @param array<int, array{role: string, content: string}> $messages */
    public function chat(array $messages, array $options = []): string;

    /** @return array{healthy: bool, provider: string, model: string, error: ?string} */
    public function health(): array;

    public function providerName(): string;

    public function model(): string;
}
