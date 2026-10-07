<?php

namespace App\DTO\Note;

use App\Http\Requests\Note\CreateNoteRequest;

readonly class CreateNoteData
{
    public function __construct(
        public string $note,
        public int $workerCount,
        public bool $notifyAdmin,
        public array $attachments,
    ) {
    }

    public static function fromRequest(CreateNoteRequest $request): self
    {
        return new self(
            note: $request->string('note')->toString(),
            workerCount: $request->integer('worker_count'),
            notifyAdmin: $request->boolean('notify_admin'),
            attachments: $request->file('attachments', []),
        );
    }
}
