<?php

namespace App\Http\Controllers;

use App\Actions\Attachment\UploadAttachmentsAction;
use App\Http\Requests\Attachment\DeleteAttachmentRequest;
use App\Http\Requests\Attachment\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends ApiController
{
    public function __construct(
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
        private readonly AttachmentService $attachmentService,
    ) {
    }

    public function uploadToDailyLog(UploadAttachmentRequest $request, DailyLog $dailyLog,): AnonymousResourceCollection {
        /** @var Worker $worker */
        $worker = $request->user();

        $attachments = $this->uploadAttachmentsAction->execute(
            attachable: $dailyLog,
            files: $request->file('attachments', []),
            worker: $worker,
        );

        return AttachmentResource::collection($attachments);
    }

    public function uploadToExpense(UploadAttachmentRequest $request, Expense $expense,): AnonymousResourceCollection {
        /** @var Worker $worker */
        $worker = $request->user();

        $attachments = $this->uploadAttachmentsAction->execute(
            attachable: $expense,
            files: $request->file('attachments', []),
            worker: $worker,
        );

        return AttachmentResource::collection($attachments);
    }

    public function download(Attachment $attachment,): StreamedResponse {
        return $this->attachmentService->download($attachment);
    }

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
