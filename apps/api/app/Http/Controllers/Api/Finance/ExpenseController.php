<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Http\Resources\Finance\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenseService)
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.manage_expenses');
    }

    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::query()
            ->with(['submitter', 'approver', 'reimburser', 'lineItems'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('submitted_by'), fn ($query, $userId) => $query->where('submitted_by', $userId))
            ->orderByDesc('incurred_at')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return ExpenseResource::collection($expenses)->response();
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenseService->createExpense($request->validated(), $request->user());

        return ExpenseResource::make($expense->load(['lineItems', 'submitter']))->response()->setStatusCode(201);
    }

    public function show(Expense $expense): JsonResponse
    {
        return ExpenseResource::make($expense->load(['lineItems', 'submitter', 'approver', 'reimburser']))->response();
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->updateExpense($expense, $request->validated());

        return ExpenseResource::make($expense->load(['lineItems', 'submitter', 'approver', 'reimburser']))->response();
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json([], 204);
    }

    public function submit(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->submitExpense($expense, $request->user());

        return ExpenseResource::make($expense->load(['lineItems', 'submitter']))->response();
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->approveExpense($expense, $request->user());

        return ExpenseResource::make($expense->load(['lineItems', 'submitter', 'approver']))->response();
    }

    public function reimburse(Request $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->reimburseExpense($expense, $request->user());

        return ExpenseResource::make($expense->load(['lineItems', 'submitter', 'approver', 'reimburser']))->response();
    }
}
