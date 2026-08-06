<?php

namespace App\Http\Controllers;

use App\DTO\Expense\CreateExpenseData;
use App\DTO\Expense\GetExpensesData;
use App\DTO\Expense\UpdateExpenseData;
use App\Http\Requests\Expense\CreateExpenseRequest;
use App\Http\Requests\Expense\DeleteExpenseRequest;
use App\Http\Requests\Expense\GetExpensesRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\CashAdvanceResource;
use App\Http\Resources\ExpenseResource;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\FinancialSummaryResource;
use App\Services\FinancialSummaryService;

class ExpenseController extends ApiController
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly FinancialSummaryService $financialSummaryService,
    ) {
    }

    public function getAll(GetExpensesRequest $request): JsonResponse
    {
        return $this->success(
            ExpenseResource::collection(
                $this->expenseService->getAll(
                    GetExpensesData::fromRequest($request)
                )
            )
        );
    }

    public function store(DailyLog $dailyLog, CreateExpenseRequest $request): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $expense = $this->expenseService->create($dailyLog, CreateExpenseData::fromRequest($request), $worker);

        return $this->success(
            ExpenseResource::make(
                $expense->load([
                    'creator',
                    'siteManager',
                    'constructionSite',
                    'attachments',
                ])
            ),
            'Expense created successfully.'
        );
    }

    public function index(DailyLog $dailyLog, GetExpensesRequest $request,): JsonResponse
    {
        $expenses = $this->expenseService->get(
            $dailyLog,
            GetExpensesData::fromRequest($request),
        );

        $summary = $this->financialSummaryService
            ->summaryForSiteManager(
                $dailyLog->siteManager
            );

        return $this->success([
            'expenses' => ExpenseResource::collection(
                $expenses
            ),
            'summary' => FinancialSummaryResource::make(
                $summary
            ),
        ]);
    }

    public function update(DailyLog $dailyLog, Expense $expense, UpdateExpenseRequest $request): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            ExpenseResource::make(
                $this->expenseService->update($dailyLog, $expense, UpdateExpenseData::fromRequest($request), $worker)
            ),
            'Expense updated successfully.'
        );
    }

    public function destroy(DailyLog $dailyLog, Expense $expense, DeleteExpenseRequest $request): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->expenseService->delete(dailyLog: $dailyLog, expense: $expense, currentWorker: $worker, reason: $request->string('reason')->toString());

        return $this->success(
            message: 'Expense deleted successfully.'
        );
    }

    public function history(
        DailyLog $dailyLog,
        GetExpensesRequest $request,
    ): JsonResponse {

        $expenses = $this->expenseService->getHistory(
            $dailyLog,
            GetExpensesData::fromRequest($request),
        );

        $summary = $this->financialSummaryService
            ->summaryForSiteManager(
                $dailyLog->siteManager,
            );

        $cashAdvances = $this->financialSummaryService
            ->cashAdvancesForSiteManager(
                $dailyLog->siteManager,
            );

        return $this->success([
            'expenses' => ExpenseResource::collection(
                $expenses,
            ),

            'summary' => FinancialSummaryResource::make(
                $summary,
            ),

            'cash_advances' => CashAdvanceResource::collection(
                $cashAdvances,
            ),
        ]);
    }
}
