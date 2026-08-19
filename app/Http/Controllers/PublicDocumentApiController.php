<?php

namespace App\Http\Controllers;

use App\Services\PublicDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDocumentApiController extends Controller
{
    public function index(Request $request, PublicDocumentService $documentService): JsonResponse
    {
        $perPage = 6;
        $paginator = $documentService->getPaginatedDocuments($perPage);

        $documents = collect($paginator->items())->map(
            fn ($doc) => $documentService->formatDocument($doc)
        )->values();

        return response()->json([
            'data' => $documents,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'has_previous' => $paginator->currentPage() > 1,
            'has_next' => $paginator->hasMorePages(),
        ]);
    }
}
