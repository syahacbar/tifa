<?php

namespace App\Providers;

use App\Contracts\LlmProvider;
use App\Services\GroqLlmProvider;
use App\Services\OllamaLlmProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, function ($app): LlmProvider {
            return match (config('services.tifa_ai.provider')) {
                'groq' => $app->make(GroqLlmProvider::class),
                'ollama' => $app->make(OllamaLlmProvider::class),
                default => throw new \InvalidArgumentException('TIFA_LLM_PROVIDER tidak didukung.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
