<?php

namespace App\Console\Commands;

use App\Contracts\LlmProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tifa:llm-status')]
#[Description('Periksa koneksi provider LLM TIFAA tanpa menampilkan credential')]
class LlmStatusCommand extends Command
{
    public function handle(LlmProvider $provider): int
    {
        $startedAt = hrtime(true);
        $health = $provider->health();
        $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $this->components->twoColumnDetail('Provider', $health['provider']);
        $this->components->twoColumnDetail('Model', $health['model'] !== '' ? $health['model'] : '-');
        $this->components->twoColumnDetail('Status', $health['healthy'] ? 'reachable' : 'unreachable');
        $this->components->twoColumnDetail('Latency', $latency.' ms');
        if (! $health['healthy']) $this->error($health['error'] ?? 'Provider tidak dapat dijangkau.');

        return $health['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
