<?php

namespace App\Services;

use App\Actions\Document\CreateDocumentAction;
use App\Actions\Document\DeleteDocumentAction;
use App\Actions\Document\DownloadDocumentAction;
use App\Actions\Document\UpdateDocumentAction;
use App\DTO\Document\CreateDocumentData;
use App\DTO\Document\GetDocumentsData;
use App\DTO\Document\UpdateDocumentData;
use App\Models\Document;
use App\Models\Worker;
use App\QueryFilters\DocumentFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    public function __construct(
        private readonly CreateDocumentAction $createDocumentAction,
        private readonly UpdateDocumentAction $updateDocumentAction,
        private readonly DeleteDocumentAction $deleteDocumentAction,
        private readonly DownloadDocumentAction $downloadDocumentAction,
    ) {
    }

    /**
     * Base query with permissions.
     */
    private function queryForWorker(
        Worker $currentWorker,
    ): Builder {

        $query = Document::query()
            ->whereBelongsTo($currentWorker->company)
            ->with([
                'constructionSite',
                'siteManager',
                'uploader',
            ]);

        if ($currentWorker->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($currentWorker) {

            $query
                ->whereNull('site_manager_id')
                ->orWhere(
                    'site_manager_id',
                    $currentWorker->id,
                );

        });
    }

    /**
     * Query for document listing.
     */
    private function query(
        Worker $currentWorker,
        GetDocumentsData $data,
    ): Builder {

        return $this->queryForWorker($currentWorker)
            ->where(
                'construction_site_id',
                $data->constructionSiteId,
            );
    }

    public function create(
        CreateDocumentData $data,
        Worker $currentWorker,
    ): Collection {

        abort_unless(
            $currentWorker->isAdmin(),
            403,
        );

        return DB::transaction(function () use (
            $data,
            $currentWorker,
        ) {

            $documents = new Collection();

            foreach ($data->files as $file) {

                $documents->push(
                    $this->createDocumentAction->execute(
                        data: $data,
                        file: $file,
                        currentWorker: $currentWorker,
                    )
                );

            }

            return $documents;
        });
    }

    public function get(
        Worker $currentWorker,
        GetDocumentsData $data,
    ): Collection {

        return (new DocumentFilter($data))
            ->apply(
                $this->query(
                    $currentWorker,
                    $data,
                )
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(
        Worker $currentWorker,
        int $id,
    ): ?Document {

        return $this->queryForWorker($currentWorker)
            ->whereKey($id)
            ->first();
    }

    public function update(
        Document $document,
        UpdateDocumentData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): Document {

        abort_unless(
            $currentWorker->isAdmin(),
            403,
        );

        return $this->updateDocumentAction->execute(
            document: $document,
            data: $data,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function delete(
        Document $document,
        Worker $currentWorker,
        string $reason,
    ): void {

        abort_unless(
            $currentWorker->isAdmin(),
            403,
        );

        $this->deleteDocumentAction->execute(
            document: $document,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function download(
        Document $document,
        Worker $currentWorker,
    ): StreamedResponse {

        abort_unless(
            $this->queryForWorker($currentWorker)
                ->whereKey($document->id)
                ->exists(),
            403,
        );

        return $this->downloadDocumentAction->execute(
            $document,
        );
    }
}
