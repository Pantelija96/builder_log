<?php

namespace App\DTO\Requests;

use Illuminate\Foundation\Http\FormRequest;

readonly class ListQueryData
{
    public function __construct(
        public ?string $search,
        public ?string $sort,
        public string $direction,
        public int $offset,
        public int $limit,
    ) {
    }

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            search: $request->validated('search'),
            sort: $request->validated('sort'),
            direction: $request->validated('direction', 'asc'),
            offset: (int) $request->validated('offset', 0),
            limit: (int) $request->validated('limit', 20),
        );
    }
}
