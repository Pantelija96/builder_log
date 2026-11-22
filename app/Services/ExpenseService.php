<?php

namespace App\Services;

use App\Actions\Expense\CreateAdminExpenseAction;
use App\Actions\Expense\CreateExpenseAction;
use App\Actions\Expense\DeleteExpenseAction;
use App\Actions\Expense\UpdateExpenseAction;
use App\DTO\Expense\CreateAdminExpenseData;
use App\DTO\Expense\CreateExpenseData;
use App\DTO\Expense\GetExpensesData;
use App\DTO\Expense\UpdateExpenseData;
use App\Enums\WorkerRole;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\QueryFilters\ExpenseFilter;
use App\Traits\EnsuresWorkerCanManageDailyLog;
use Illuminate\Database\Eloquent\Collection;

class ExpenseService
{
    use EnsuresWorkerCanManageDailyLog;

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
                'dailyLog.siteManagers',
                'attachments',
            ]);
    }

    public function __construct(
        private readonly CreateExpenseAction $createExpenseAction,
        private readonly CreateAdminExpenseAction $createAdminExpenseAction,
        private readonly UpdateExpenseAction $updateExpenseAction,
        private readonly DeleteExpenseAction $deleteExpenseAction,
        private readonly FinancialSummaryService $financialSummaryService,
    ) {}

    public function create(DailyLog $dailyLog, CreateExpenseData $data, Worker $currentWorker): Expense
    {
        return $this->createExpenseAction->execute(
            $dailyLog,
            $data,
            $currentWorker,
        );
    }

    public function createByAdmin(CreateAdminExpenseData $data, Worker $currentWorker): Expense
    {
        return $this->createAdminExpenseAction->execute(
            $data,
            $currentWorker,
        );
    }

    public function findById(DailyLog $dailyLog, int $id): ?Expense
    {
        return $this->query($dailyLog)->find($id);
    }

    public function get(DailyLog $dailyLog, GetExpensesData $data, Worker $worker): Collection
    {
        $this->ensureCanRead($dailyLog, $worker);

        return (new ExpenseFilter($data))
            ->apply(Expense::query()->whereBelongsTo($dailyLog)->where('company_id', $dailyLog->company_id))
            ->get();
    }

    public function getAll(GetExpensesData $data, Worker $worker): Collection
    {
        return (new ExpenseFilter($data))
            ->apply($this->queryAll()->where('company_id', $worker->company_id)
                ->when(! $worker->isAdmin(), fn ($query) => $query->whereHas('dailyLog', fn ($logs) => $logs->visibleTo($worker))))
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function update(DailyLog $dailyLog, Expense $expense, UpdateExpenseData $data, Worker $currentWorker, ?string $reason = null): Expense
    {
        return $this->updateExpenseAction->execute(
            $dailyLog,
            $expense,
            $data,
            $currentWorker,
            $reason
        );
    }

    public function delete(DailyLog $dailyLog, Expense $expense, Worker $currentWorker, string $reason): void
    {
        $this->deleteExpenseAction->execute(
            $dailyLog,
            $expense,
            $currentWorker,
            $reason,
        );
    }

    public function getHistory(DailyLog $dailyLog, GetExpensesData $data, Worker $worker): Collection
    {
        $this->ensureCanRead($dailyLog, $worker);

        $query = $this->queryAll()
            ->where('company_id', $dailyLog->company_id)
            ->where('construction_site_id', $dailyLog->construction_site_id)
            ->when(! $worker->isAdmin(), fn ($query) => $query->whereHas('dailyLog', fn ($logs) => $logs->visibleTo($worker)))
            ->when($worker->isSiteManager(), fn ($query) => $query->where('site_manager_id', $worker->id));

        return (new ExpenseFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function getDailyReport(DailyLog $dailyLog, GetExpensesData $data, Worker $worker): array
    {
        return [
            'expenses' => $this->get($dailyLog, $data, $worker),
            'summary' => $this->financialSummaryService->summaryForSiteManager($worker),
        ];
    }

    public function getHistoryReport(DailyLog $dailyLog, GetExpensesData $data, Worker $worker): array
    {
        return [
            'expenses' => $this->getHistory($dailyLog, $data, $worker),
            'summary' => $this->financialSummaryService->summaryForSiteManager($worker),
            'cash_advances' => $this->financialSummaryService->cashAdvancesForSiteManager($worker),
        ];
    }

    public function getAdminExpenses(GetExpensesData $data, Worker $currentWorker): array
    {
        $query = $this->queryAll()
            ->where('company_id', $currentWorker->company_id)
            ->whereHas('creator', function ($query) {
                $query->where('role', WorkerRole::ADMIN);
            });

        $query = (new ExpenseFilter($data))->apply($query);

        $total = (clone $query)->sum('amount');

        $expenses = $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();

        return [
            'expenses' => $expenses,
            'total' => round((float) $total, 2),
        ];
    }
}
