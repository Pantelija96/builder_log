<?php

namespace App\DTO\Expense;

use App\Http\Requests\Expense\UpdateExpenseRequest;

readonly class UpdateExpenseData
{
    public function __construct(public string $title, public ?string $description, public float $amount,) {
    }

    public static function fromRequest(UpdateExpenseRequest $request,): self {
        return new self(
            title: $request->string('title')->toString(),

            description: $request->filled('description')
                ? $request->string('description')->toString()
                : null,

            amount: $request->float('amount'),
        );
    }
}
