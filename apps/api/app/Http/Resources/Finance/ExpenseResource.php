<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Expense */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'incurred_at' => $this->incurred_at?->toDateString(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'reimbursed_at' => $this->reimbursed_at?->toIso8601String(),
            'line_items' => ExpenseLineItemResource::collection($this->whenLoaded('lineItems')),
            'submitter' => $this->whenLoaded('submitter', fn () => [
                'id' => $this->submitter?->id,
                'name' => $this->submitter?->name,
                'email' => $this->submitter?->email,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => [
                'id' => $this->approver?->id,
                'name' => $this->approver?->name,
                'email' => $this->approver?->email,
            ]),
            'reimburser' => $this->whenLoaded('reimburser', fn () => [
                'id' => $this->reimburser?->id,
                'name' => $this->reimburser?->name,
                'email' => $this->reimburser?->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
