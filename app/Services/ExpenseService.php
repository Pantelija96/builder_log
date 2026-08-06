<?php

namespace App\Services;

use App\Actions\Expense\CreateExpenseAction;
use App\Actions\Expense\DeleteExpenseAction;
use App\Actions\Expense\UpdateExpenseAction;
use App\DTO\Expense\CreateExpenseData;
use App\DTO\Expense\GetExpensesData;
use App\DTO\Expense\UpdateExpenseData;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\QueryFilters\ExpenseFilter;
use Illuminate\Database\Eloquent\Collection;

class ExpenseService
{
    private function query(DailyLog $dailyLog)
    {
        return Expense::query()
            ->whereBelongsTo($dailyLog)
            ->with([
                'creator',
                'siteManager',
                'attachments',
            ]);
    }

    private function queryAll()
    {
        return Expense::query()
            ->with([
                'creator',
                'siteManager',
                'constructionSite',
                'dailyLog',
                'attachments',
            ]);
    }

    public function __construct(
        private readonly CreateExpenseAction $createExpenseAction,
        private readonly UpdateExpenseAction $updateExpenseAction,
        private readonly DeleteExpenseAction $deleteExpenseAction,
    ) {
    }

    public function create(DailyLog $dailyLog, CreateExpenseData $data, Worker $currentWorker,): Expense {
        return $this->createExpenseAction->execute(
            $dailyLog,
            $data,
            $currentWorker,
        );
    }

    public function findById(DailyLog $dailyLog, int $id,): ?Expense {
        return $this->query($dailyLog)->find($id);
    }

    public function get(DailyLog $dailyLog, GetExpensesData $data,): Collection {
        return (new ExpenseFilter($data))
            ->apply(
                Expense::query()
                    ->where(
                        'site_manager_id',
                        $dailyLog->site_manager_id,
                    )
                    ->where(
                        'construction_site_id',
                        $dailyLog->construction_site_id,
                    )
            )
            ->get();
    }

    public function getAll(GetExpensesData $data): Collection
    {
        return (new ExpenseFilter($data))
            ->apply($this->queryAll())
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function update(DailyLog $dailyLog, Expense $expense, UpdateExpenseData $data, Worker $currentWorker, ?string $reason = null,): Expense {
        return $this->updateExpenseAction->execute(
            $dailyLog,
            $expense,
            $data,
            $currentWorker,
            $reason
        );
    }

    public function delete(DailyLog $dailyLog, Expense $expense, Worker $currentWorker, string $reason,): void {
        $this->deleteExpenseAction->execute(
            $dailyLog,
            $expense,
            $currentWorker,
            $reason,
        );
    }

    public function getHistory(
        DailyLog $dailyLog,
        GetExpensesData $data,
    ): Collection {

        return (new ExpenseFilter($data))
            ->apply(
                Expense::query()
                    ->where(
                        'site_manager_id',
                        $dailyLog->site_manager_id,
                    )
                    ->where(
                        'construction_site_id',
                        $dailyLog->construction_site_id,
                    )
                    ->with([
                        'creator',
                        'siteManager',
                        'constructionSite',
                        'dailyLog',
                        'attachments',
                    ])
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
}
