<?php

namespace App\Services;

use App\Actions\Note\CreateNoteAction;
use App\Actions\Note\DeleteNoteAction;
use App\Actions\Note\UpdateNoteAction;
use App\DTO\Note\CreateNoteData;
use App\DTO\Note\GetNotesData;
use App\DTO\Note\UpdateNoteData;
use App\Models\DailyLog;
use App\Models\Note;
use App\Models\Worker;
use App\QueryFilters\NoteFilter;
use Illuminate\Database\Eloquent\Collection;

class NoteService
{
    public function __construct(
        private readonly CreateNoteAction $createNoteAction,
        private readonly UpdateNoteAction $updateNoteAction,
        private readonly DeleteNoteAction $deleteNoteAction,
    ) {
    }

    private function query(DailyLog $dailyLog)
    {
        return Note::query()
            ->whereBelongsTo($dailyLog)
            ->with([
                'creator',
                'siteManager',
                'constructionSite',
                'dailyLog',
                'attachments',
            ]);
    }

    public function get(
        DailyLog $dailyLog,
        GetNotesData $data,
    ): Collection {

        return (new NoteFilter($data))
            ->apply($this->query($dailyLog))
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function getAll(GetNotesData $data,): Collection
    {
        $query = Note::query()
            ->with([
                'creator',
                'siteManager',
                'constructionSite',
                'dailyLog',
                'attachments',
            ]);

        return (new NoteFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function create(
        DailyLog $dailyLog,
        CreateNoteData $data,
        Worker $worker,
    ): Note {

        return $this->createNoteAction->execute(
            dailyLog: $dailyLog,
            data: $data,
            currentWorker: $worker,
        );
    }


    public function findById(
        DailyLog $dailyLog,
        int $id,
    ): ?Note {

        return $this->query($dailyLog)
            ->find($id);
    }

    public function update(
        DailyLog $dailyLog,
        Note $note,
        UpdateNoteData $data,
        Worker $worker,
        ?string $reason,
    ): Note {

        return $this->updateNoteAction->execute(
            dailyLog: $dailyLog,
            note: $note,
            data: $data,
            currentWorker: $worker,
            reason: $reason,
        );
    }

    public function delete(
        DailyLog $dailyLog,
        Note $note,
        Worker $worker,
        string $reason,
    ): void {

        $this->deleteNoteAction->execute(
            dailyLog: $dailyLog,
            note: $note,
            currentWorker: $worker,
            reason: $reason,
        );
    }
}
