<?php

namespace App\Http\Controllers;

use App\DTO\Note\CreateNoteData;
use App\DTO\Note\GetNotesData;
use App\DTO\Note\UpdateNoteData;
use App\Http\Requests\Note\CreateNoteRequest;
use App\Http\Requests\Note\DeleteNoteRequest;
use App\Http\Requests\Note\GetNotesRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\DailyLog;
use App\Models\Note;
use App\Models\Worker;
use App\Services\NoteService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class NoteController extends ApiController
{
    public function __construct(
        private readonly NoteService $noteService,
    ) {
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    public function index(DailyLog $dailyLog, GetNotesRequest $request,): JsonResponse
    {
        return $this->success(
            NoteResource::collection(
                $this->noteService->get(
                    dailyLog: $dailyLog,
                    data: GetNotesData::fromRequest($request),
                    worker: $request->user(),
                )
            )
        );
    }

    public function getAll(GetNotesRequest $request): JsonResponse
    {
        return $this->success(
            NoteResource::collection(
                $this->noteService->getAll(
                    data: GetNotesData::fromRequest($request),
                    worker: $request->user(),
                )
            )
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Invalid request or no manager attribution available for an administrator.', type: 'array{message: string, errors?: array<string, list<string>>}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function store(
        DailyLog $dailyLog,
        CreateNoteRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $note = $this->noteService->create(
            dailyLog: $dailyLog,
            data: CreateNoteData::fromRequest($request),
            worker: $worker,
        );

        return $this->success(
            NoteResource::make($note),
            'Note created successfully.'
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function update(
        DailyLog $dailyLog,
        Note $note,
        UpdateNoteRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            NoteResource::make(
                $this->noteService->update(
                    dailyLog: $dailyLog,
                    note: $note,
                    data: UpdateNoteData::fromRequest($request),
                    worker: $worker,
                    reason: $request->string('reason')->toString(),
                )
            ),
            'Note updated successfully.'
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function destroy(
        DailyLog $dailyLog,
        Note $note,
        DeleteNoteRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->noteService->delete(
            dailyLog: $dailyLog,
            note: $note,
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Note deleted successfully.'
        );
    }
}
