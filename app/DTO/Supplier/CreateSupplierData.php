<?php

namespace App\DTO\Supplier;

use App\Http\Requests\Supplier\CreateSupplierRequest;

readonly class CreateSupplierData
{
    public function __construct(
        public string $name,
        public ?string $pib,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public ?string $contactFirstName,
        public ?string $contactLastName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public bool $isActive,
    ) {
    }

    public static function fromRequest(
        CreateSupplierRequest $request,
    ): self {
        return new self(
            name:
            $request->validated('name'),

            pib:
            $request->validated('pib'),

            address:
            $request->validated('address'),

            phone:
            $request->validated('phone'),

            email:
            $request->validated('email'),

            contactFirstName:
            $request->validated(
                'contact_first_name'
            ),

            contactLastName:
            $request->validated(
                'contact_last_name'
            ),

            contactEmail:
            $request->validated(
                'contact_email'
            ),

            contactPhone:
            $request->validated(
                'contact_phone'
            ),

            isActive:
            $request->boolean(
                'is_active',
                true
            ),
        );
    }
}
