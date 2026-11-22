<?php

namespace App\Services;

use App\Actions\CashAdvance\CreateCashAdvanceAction;
use App\Actions\CashAdvance\DeleteCashAdvanceAction;
use App\Actions\CashAdvance\UpdateCashAdvanceAction;
use App\DTO\CashAdvance\CreateCashAdvanceData;
use App\DTO\CashAdvance\GetCashAdvancesData;
use App\DTO\CashAdvance\UpdateCashAdvanceData;
use App\Models\CashAdvance;
use App\Models\Worker;
use App\QueryFilters\CashAdvanceFilter;
use Illuminate\Database\Eloquent\Collection;

class CashAdvanceService
{
    public function __construct(
        private readonly CreateCashAdvanceAction $createAction,
        private readonly UpdateCashAdvanceAction $updateAction,
        private readonly DeleteCashAdvanceAction $deleteAction,
    ) {}

    private function query(Worker $worker)
    {
        return CashAdvance::query()
            ->with([
                'siteManager' => fn ($manager) => $manager->where('company_id', $worker->company_id),
                'creator' => fn ($creator) => $creator->where('company_id', $worker->company_id),
            ]);
    }

    public function findById(int $id, Worker $worker): ?CashAdvance
    {
        return $this->query($worker)->where('company_id', $worker->company_id)->find($id);
    }

    public function get(GetCashAdvancesData $data, Worker $worker): Collection
    {
        return (new CashAdvanceFilter($data))
            ->apply($this->query($worker)->where('company_id', $worker->company_id)
                ->whereHas('siteManager', fn ($manager) => $manager->withTrashed()->where('company_id', $worker->company_id)))
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function create(
        CreateCashAdvanceData $data,
        Worker $worker,
    ): CashAdvance {

        return $this->createAction->execute(
            $data,
            $worker,
        );
    }

    public function update(
        CashAdvance $cashAdvance,
        UpdateCashAdvanceData $data,
        Worker $worker,
        ?string $reason,
    ): CashAdvance {

        abort_unless($cashAdvance->company_id === $worker->company_id, 404);

        return $this->updateAction->execute(
            $cashAdvance,
            $data,
            $worker,
            $reason,
        );
    }

    public function delete(
        CashAdvance $cashAdvance,
        Worker $worker,
        string $reason,
    ): void {

        abort_unless($cashAdvance->company_id === $worker->company_id, 404);

        $this->deleteAction->execute(
            $cashAdvance,
            $worker,
            $reason,
        );
    }
}
