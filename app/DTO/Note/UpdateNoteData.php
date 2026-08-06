<?php

namespace App\DTO\Note;

use App\Http\Requests\Note\UpdateNoteRequest;

readonly class UpdateNoteData
{
    public function __construct(
        public string $note,
        public bool $notifyAdmin,
        public array $attachments,
        public array $deleteAttachments,
    ) {
    }

    public static function fromRequest(UpdateNoteRequest $request): self
    {
        return new self(
            note: $request->string('note')->toString(),
            notifyAdmin: $request->boolean('notify_admin'),
            attachments: $request->file('attachments', []),
            deleteAttachments: $request->input('delete_attachments', [],),
        );
    }
}
