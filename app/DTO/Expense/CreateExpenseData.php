<?php

namespace App\DTO\Expense;

use App\Http\Requests\Expense\CreateExpenseRequest;

readonly class CreateExpenseData
{
    public function __construct(public string $title, public ?string $description, public float $amount, public readonly array $attachments,) {
    }

    public static function fromRequest(CreateExpenseRequest $request,): self {
        return new self(
            title: $request->string('title')->toString(),
            description: $request->filled('description')
                ? $request->string('description')->toString()
                : null,
            amount: $request->float('amount'),
            attachments: $request->file('attachments', []),
        );
    }
}
