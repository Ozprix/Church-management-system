<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FinanceReport */
class FinanceReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'disk' => $this->disk,
            'file_path' => $this->file_path,
            'filters' => $this->filters,
            'failure_reason' => $this->failure_reason,
            'generated_at' => $this->generated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'requested_by' => $this->whenLoaded('requester', function () {
                return [
                    'id' => $this->requester?->id,
                    'name' => $this->requester?->name,
                    'email' => $this->requester?->email,
                ];
            }),
            'download_url' => $this->when(
                $this->status === 'completed' && $this->file_path,
                fn () => route('finance.reports.download', ['financeReport' => $this->id], false)
            ),
            'label' => $this->labelForType(),
        ];
    }

    private function labelForType(): string
    {
        return match ($this->type) {
            'donations' => 'Donations summary',
            'pledges' => 'Pledges summary',
            'donor-statement' => 'Donor statement',
            'monthly-statement' => 'Monthly giving statement',
            'balance-sheet' => 'Balance sheet',
            'donor-letters' => 'Donor thank-you letters',
            default => ucfirst(str_replace('-', ' ', (string) $this->type)),
        };
    }
}
