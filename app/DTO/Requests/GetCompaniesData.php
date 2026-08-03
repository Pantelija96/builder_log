<?php

namespace App\DTO\Requests;

use App\Http\Requests\Get\GetCompaniesRequest;

readonly class GetCompaniesData
{
    public function __construct(
        public ListQueryData $list,
        public ?string $name,
        public ?string $pib,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
    ) {
    }

    public static function fromRequest(GetCompaniesRequest $request): self
    {
        return new self(
            list: ListQueryData::fromRequest($request),
            name: $request->validated('name'),
            pib: $request->validated('pib'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),
            address: $request->validated('address'),
        );
    }
}
