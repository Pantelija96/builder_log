<?php

namespace App\DTO\CashAdvance;

use App\Http\Requests\CashAdvance\CreateCashAdvanceRequest;
use Carbon\Carbon;

readonly class CreateCashAdvanceData
{
    public function __construct(
        public int $siteManagerId,
        public float $amount,
        public Carbon $date,
    ) {
    }

    public static function fromRequest(CreateCashAdvanceRequest $request): self
    {
        return new self(
            siteManagerId: $request->integer('site_manager_id'),
            amount: $request->float('amount'),
            date: Carbon::parse($request->date),
        );
    }
}
