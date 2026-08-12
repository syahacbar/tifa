<?php

namespace App\Http\Controllers;

use App\Exceptions\DatasetUnavailableException;
use App\Exceptions\OllamaException;
use App\Exceptions\TifaIntentException;
use App\Services\TifaAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TifaAssistantController extends Controller
{
    public function ask(Request $request, TifaAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        try {
            return response()->json($assistant->ask($validated['question']));
        } catch (OllamaException) {
            return response()->json(['message' => 'Layanan Ollama tidak tersedia.'], 503);
        } catch (TifaIntentException) {
            return response()->json(['message' => 'Pertanyaan tidak dapat dipahami sebagai query data TIFA.'], 422);
        } catch (DatasetUnavailableException) {
            return response()->json(['message' => 'Dataset aktif TIFA tidak tersedia.'], 404);
        }
    }
}
