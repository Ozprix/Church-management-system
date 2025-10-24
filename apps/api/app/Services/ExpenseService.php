<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseLineItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function createExpense(array $attributes, ?User $actor = null): Expense
    {
        return DB::transaction(function () use ($attributes, $actor): Expense {
            $lineItems = $attributes['line_items'] ?? [];

            $payload = Arr::only($attributes, [
                'tenant_id',
                'submitted_by',
                'title',
                'description',
                'currency',
                'status',
                'incurred_at',
                'metadata',
            ]);

            $payload['status'] = $payload['status'] ?? Expense::STATUS_DRAFT;
            $payload['submitted_by'] = $payload['submitted_by'] ?? $actor?->id;
            $payload['currency'] = $payload['currency'] ?? 'USD';

            /** @var Expense $expense */
            $expense = Expense::create($payload);

            $this->syncLineItems($expense, $lineItems);

            return $expense->load('lineItems');
        });
    }

    public function updateExpense(Expense $expense, array $attributes): Expense
    {
        $this->ensureEditable($expense);

        return DB::transaction(function () use ($expense, $attributes): Expense {
            $lineItems = $attributes['line_items'] ?? null;

            $expense->fill(Arr::only($attributes, [
                'title',
                'description',
                'currency',
                'incurred_at',
                'metadata',
            ]));

            $expense->save();

            if ($lineItems !== null) {
                $this->syncLineItems($expense, $lineItems);
            } else {
                $this->refreshTotals($expense);
            }

            return $expense->fresh(['lineItems']);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $lineItems
     */
    protected function syncLineItems(Expense $expense, array $lineItems): void
    {
        $expense->lineItems()->delete();

        $total = 0.0;

        foreach ($lineItems as $item) {
            if (empty($item['description']) || empty($item['amount'])) {
                throw ValidationException::withMessages([
                    'line_items' => ['Each line item requires a description and amount.'],
                ]);
            }

            $amount = round((float) $item['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'line_items' => ['Line item amounts must be greater than zero.'],
                ]);
            }

            ExpenseLineItem::create([
                'expense_id' => $expense->id,
                'description' => $item['description'],
                'amount' => $amount,
                'category' => $item['category'] ?? null,
                'metadata' => $item['metadata'] ?? null,
            ]);

            $total += $amount;
        }

        $expense->forceFill([
            'total_amount' => $total,
        ])->save();
    }

    public function submitExpense(Expense $expense, User $actor): Expense
    {
        $this->ensureDraftOrPending($expense);

        if ($expense->lineItems()->count() === 0) {
            throw ValidationException::withMessages([
                'line_items' => ['Add at least one line item before submitting.'],
            ]);
        }

        $expense->forceFill([
            'status' => Expense::STATUS_PENDING,
            'submitted_at' => Carbon::now(),
            'submitted_by' => $expense->submitted_by ?? $actor->id,
        ])->save();

        return $expense->fresh(['lineItems', 'submitter']);
    }

    public function approveExpense(Expense $expense, User $actor): Expense
    {
        if ($expense->status !== Expense::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending expenses can be approved.'],
            ]);
        }

        $expense->forceFill([
            'status' => Expense::STATUS_APPROVED,
            'approved_at' => Carbon::now(),
            'approved_by' => $actor->id,
        ])->save();

        $this->notifySubmitter($expense, sprintf('Expense "%s" approved', $expense->title), sprintf(
            'Your expense request for %s %s has been approved.',
            $expense->currency,
            number_format((float) $expense->total_amount, 2)
        ));

        return $expense->fresh(['lineItems', 'submitter', 'approver']);
    }

    public function reimburseExpense(Expense $expense, User $actor): Expense
    {
        if ($expense->status !== Expense::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => ['Only approved expenses can be reimbursed.'],
            ]);
        }

        $expense->forceFill([
            'status' => Expense::STATUS_REIMBURSED,
            'reimbursed_at' => Carbon::now(),
            'reimbursed_by' => $actor->id,
        ])->save();

        $this->notifySubmitter($expense, sprintf('Expense "%s" reimbursed', $expense->title), sprintf(
            'Your reimbursement for %s %s has been processed.',
            $expense->currency,
            number_format((float) $expense->total_amount, 2)
        ));

        return $expense->fresh(['lineItems', 'submitter', 'reimburser']);
    }

    protected function ensureEditable(Expense $expense): void
    {
        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_PENDING], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only draft or pending expenses can be updated.'],
            ]);
        }
    }

    protected function ensureDraftOrPending(Expense $expense): void
    {
        if (! in_array($expense->status, [Expense::STATUS_DRAFT, Expense::STATUS_PENDING], true)) {
            throw ValidationException::withMessages([
                'status' => ['Expense cannot be submitted once processed.'],
            ]);
        }
    }

    protected function refreshTotals(Expense $expense): void
    {
        $total = (float) $expense->lineItems()->sum('amount');

        $expense->forceFill([
            'total_amount' => $total,
        ])->save();
    }

    protected function notifySubmitter(Expense $expense, string $subject, string $body): void
    {
        $submitter = $expense->submitter;

        if (! $submitter) {
            return;
        }

        $this->notificationService->queue([
            'tenant_id' => $expense->tenant_id,
            'member_id' => null,
            'channel' => 'email',
            'recipient' => $submitter->email ?? 'unknown@example.com',
            'subject' => $subject,
            'body' => $body,
            'payload' => [
                'expense_id' => $expense->id,
                'status' => $expense->status,
                'total_amount' => $expense->total_amount,
                'currency' => $expense->currency,
            ],
        ]);
    }
}
