<?php

namespace App\Http\Controllers;

use App\DTO\Document\CreateDocumentData;
use App\DTO\Document\GetDocumentsData;
use App\DTO\Document\UpdateDocumentData;
use App\Http\Requests\Document\CreateDocumentRequest;
use App\Http\Requests\Document\DeleteDocumentRequest;
use App\Http\Requests\Document\GetDocumentsRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Worker;
use App\Services\DocumentService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends ApiController
{
    public function __construct(
        private readonly DocumentService $documentService,
    ) {}

    public function getAll(
        GetDocumentsRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            DocumentResource::collection(
                $this->documentService->get(
                    currentWorker: $worker,
                    data: GetDocumentsData::fromRequest($request),
                )
            )
        );
    }

    #[Response(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'The target or request does not meet validation requirements.', type: 'array{message: string}')]
    public function store(
        CreateDocumentRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            DocumentResource::collection(
                $this->documentService->create(
                    data: CreateDocumentData::fromRequest($request),
                    currentWorker: $worker,
                )
            ),
            'Documents uploaded successfully.'
        );
    }

    #[Response(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'The target or request does not meet validation requirements.', type: 'array{message: string}')]
    public function update(
        Document $document,
        UpdateDocumentRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            DocumentResource::make(
                $this->documentService->update(
                    document: $document,
                    data: UpdateDocumentData::fromRequest($request),
                    currentWorker: $worker,
                )
            ),
            'Document updated successfully.'
        );
    }

    #[Response(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    public function destroy(
        Document $document,
        DeleteDocumentRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->documentService->delete(
            document: $document,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Document deleted successfully.'
        );
    }

    #[Response(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    public function download(
        Document $document,
    ): StreamedResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->documentService->download(
            document: $document,
            currentWorker: $worker,
        );
    }
}
