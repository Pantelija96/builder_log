<?php

namespace App\DTO\CashAdvance;

use App\Http\Requests\CashAdvance\UpdateCashAdvanceRequest;
use Carbon\Carbon;

readonly class UpdateCashAdvanceData
{
    public function __construct(
        public int $siteManagerId,
        public float $amount,
        public Carbon $date,
    ) {
    }

    public static function fromRequest(UpdateCashAdvanceRequest $request): self
    {
        return new self(
            siteManagerId: $request->integer('site_manager_id'),
            amount: $request->float('amount'),
            date: Carbon::parse($request->date),
        );
    }
}
