<?php

namespace App\Listeners;

use App\Events\AttendanceRecorded;
use App\Models\AttendanceRecord;
use App\Models\Service;
use App\Models\User;
use App\Models\VisitorFollowup;
use App\Models\VisitorWorkflow;
use App\Services\NotificationService;
use App\Services\VisitorAutomationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceAutomations
{
    public function __construct(
        private readonly VisitorAutomationService $visitorAutomation,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function handle(AttendanceRecorded $event): void
    {
        /** @var AttendanceRecord $record */
        $record = $event->record->loadMissing('member', 'gathering.service');

        if (! $record->member || ! $record->gathering) {
            return;
        }

        if ($record->status === 'present') {
            $this->resolveAbsenceFollowups($record);

            return;
        }

        if ($record->status !== 'absent') {
            return;
        }

        $service = $record->gathering->service;

        if (! $service || ! $service->absence_threshold) {
            return;
        }

        $threshold = (int) $service->absence_threshold;
        if ($threshold <= 0) {
            return;
        }

        $consecutive = AttendanceRecord::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('member_id', $record->member_id)
            ->whereHas('gathering', function ($query) use ($service): void {
                $query->where('service_id', $service->id);
            })
            ->orderByDesc('created_at')
            ->limit($threshold)
            ->get();

        if ($consecutive->count() < $threshold) {
            return;
        }

        if ($consecutive->every(fn (AttendanceRecord $row) => $row->status === 'absent')) {
            $this->triggerAbsenceFollowup($record, $service, $consecutive);
        }
    }

    private function resolveAbsenceFollowups(AttendanceRecord $record): void
    {
        VisitorFollowup::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('member_id', $record->member_id)
            ->where('metadata->trigger', 'attendance_absence')
            ->whereIn('status', ['pending', 'in_progress'])
            ->get()
            ->each(function (VisitorFollowup $followup) use ($record): void {
                $followup->status = 'completed';
                $followup->completed_at = now();
                $followup->metadata = array_merge($followup->metadata ?? [], [
                    'resolved_at' => now()->toIso8601String(),
                    'resolved_gathering_id' => $record->gathering_id,
                ]);
                $followup->save();
            });
    }

    private function triggerAbsenceFollowup(
        AttendanceRecord $record,
        Service $service,
        Collection $consecutive
    ): void {
        $member = $record->member;
        if (! $member) {
            return;
        }

        $workflow = $this->resolveWorkflow($service);
        $followupCreated = false;

        if ($workflow) {
            $existing = VisitorFollowup::query()
                ->where('tenant_id', $record->tenant_id)
                ->where('member_id', $member->id)
                ->where('workflow_id', $workflow->id)
                ->where('metadata->trigger', 'attendance_absence')
                ->whereIn('status', ['pending', 'in_progress'])
                ->exists();

            if (! $existing) {
                try {
                    $followup = $this->visitorAutomation->startFollowup($member, $workflow);
                    $followup->metadata = array_merge($followup->metadata ?? [], [
                        'trigger' => 'attendance_absence',
                        'service_id' => $service->id,
                        'last_absence_record_id' => $record->id,
                        'absences' => $consecutive->pluck('gathering_id')->all(),
                    ]);
                    $followup->save();
                    $followupCreated = true;
                } catch (\Throwable $exception) {
                    Log::error('Unable to start absence follow-up workflow', [
                        'member_id' => $member->id,
                        'service_id' => $service->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        if ($followupCreated || ! $workflow) {
            $this->notifyStaff($record, $service, $consecutive);
        }
    }

    private function resolveWorkflow(Service $service): ?VisitorWorkflow
    {
        $preferredWorkflowId = data_get($service->metadata, 'absence_workflow_id');

        if ($preferredWorkflowId) {
            $workflow = VisitorWorkflow::query()
                ->where('tenant_id', $service->tenant_id)
                ->where('is_active', true)
                ->find($preferredWorkflowId);

            if ($workflow) {
                return $workflow;
            }
        }

        return VisitorWorkflow::query()
            ->where('tenant_id', $service->tenant_id)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();
    }

    private function notifyStaff(AttendanceRecord $record, Service $service, Collection $consecutive): void
    {
        $recipient = User::query()
            ->where('tenant_id', $record->tenant_id)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['visitor_manager', 'admin']))
            ->value('email');

        if (! $recipient) {
            return;
        }

        $member = $record->member;

        $subject = sprintf(
            'Attendance alert: %s',
            trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''))
        );

        $body = sprintf(
            "%s has missed %d %s gathering(s) in a row. Latest absence was recorded for %s.",
            trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
            $consecutive->count(),
            $service->name ?? 'service',
            optional($record->gathering->starts_at)->toDayDateTimeString() ?? 'an unscheduled time'
        );

        $this->notificationService->queue([
            'tenant_id' => $record->tenant_id,
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ]);
    }
}
