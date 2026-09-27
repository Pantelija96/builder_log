<?php

namespace App\DTO\Expense;

use App\Http\Requests\Expense\CreateAdminExpenseRequest;
use Carbon\Carbon;

readonly class CreateAdminExpenseData
{
    public function __construct(
        public int $constructionSiteId,
        public string $title,
        public ?string $description,
        public float $amount,
        public Carbon $date,
        public array $attachments,
    ) {
    }

    public static function fromRequest(
        CreateAdminExpenseRequest $request,
    ): self {
        return new self(
            constructionSiteId: $request->integer('construction_site_id'),
            title: $request->string('title')->toString(),
            description: $request->filled('description')
                ? $request->string('description')->toString()
                : null,
            amount: $request->float('amount'),
            date: $request->date('date'),
            attachments: $request->file('attachments', []),
        );
    }
}
