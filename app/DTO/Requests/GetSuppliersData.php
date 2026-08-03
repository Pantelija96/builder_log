<?php

namespace App\DTO\Requests;

use App\Http\Requests\Get\GetSuppliersRequest;

readonly class GetSuppliersData
{
    public function __construct(
        public ListQueryData $list,
        public ?string $name,
        public ?string $pib,
        public ?string $email,
        public ?string $phone,
        public ?bool $isActive,
    ) {
    }

    public static function fromRequest(GetSuppliersRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),

            name: $request->validated('name'),
            pib: $request->validated('pib'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),

            isActive: $request->has('is_active')
                ? $request->boolean('is_active')
                : null,
        );
    }
}
