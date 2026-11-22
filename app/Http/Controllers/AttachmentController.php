<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attachment\DeleteAttachmentRequest;
use App\Http\Requests\Attachment\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\AttachmentService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends ApiController
{
    public function __construct(
        private readonly AttachmentService $attachmentService,
    ) {
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function uploadToDailyLog(UploadAttachmentRequest $request, DailyLog $dailyLog,): AnonymousResourceCollection {
        /** @var Worker $worker */
        $worker = $request->user();

        $attachments = $this->attachmentService->uploadMany(
            attachable: $dailyLog,
            files: $request->file('attachments', []),
            worker: $worker,
        );

        return AttachmentResource::collection($attachments);
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function uploadToExpense(UploadAttachmentRequest $request, Expense $expense,): AnonymousResourceCollection {
        /** @var Worker $worker */
        $worker = $request->user();

        $attachments = $this->attachmentService->uploadMany(
            attachable: $expense,
            files: $request->file('attachments', []),
            worker: $worker,
        );

        return AttachmentResource::collection($attachments);
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    public function download(Request $request, Attachment $attachment): StreamedResponse {
        return $this->attachmentService->download($attachment, $request->user());
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function destroy(DeleteAttachmentRequest $request, Attachment $attachment,): \Illuminate\Http\Response
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $this->attachmentService->delete(
            attachment: $attachment,
            worker: $worker,
            reason: $request->validated('reason'),
        );

        return $this->noContent();
    }
}
