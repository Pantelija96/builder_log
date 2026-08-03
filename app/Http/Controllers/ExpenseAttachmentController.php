<?php

namespace App\Http\Controllers;

use App\DTO\Attachment\UploadAttachmentData;
use App\Http\Requests\Attachment\UploadAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Services\AttachmentService;
use Illuminate\Http\Request;

class ExpenseAttachmentController extends ApiController
{
    public function __construct(
        private readonly AttachmentService $service,
    ) {
    }

    public function store(UploadAttachmentRequest $request, DailyLog $dailyLog, Expense $expense,): AttachmentResource {

        $worker = $request->worker();

        $attachment = $this->service->upload(
            attachable: $expense,
            data: UploadAttachmentData::fromRequest($request),
            worker: $worker,
        );

        return AttachmentResource::make($attachment);
    }
}
