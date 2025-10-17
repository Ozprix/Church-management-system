<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Gathering;
use App\Models\Member;
use App\Models\Service;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function createService(array $attributes): Service
    {
        $data = Arr::only($attributes, [
            'tenant_id',
            'name',
            'slug',
            'short_code',
            'description',
            'default_location',
            'default_start_time',
            'default_duration_minutes',
            'absence_threshold',
            'metadata',
        ]);

        return Service::create($data);
    }

    public function updateService(Service $service, array $attributes): Service
    {
        $service->fill(Arr::only($attributes, [
            'name',
            'slug',
            'short_code',
            'description',
            'default_location',
            'default_start_time',
            'default_duration_minutes',
            'absence_threshold',
            'metadata',
        ]));

        $service->save();

        return $service->refresh();
    }

    public function scheduleGathering(array $attributes): Gathering
    {
        $data = Arr::only($attributes, [
            'tenant_id',
            'service_id',
            'name',
            'status',
            'starts_at',
            'ends_at',
            'location',
            'notes',
            'metadata',
        ]);

        $tenantId = $data['tenant_id'] ?? null;

        if (! $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Tenant is required to schedule a gathering.',
            ]);
        }

        $service = null;
        if (! empty($data['service_id'])) {
            $service = Service::query()->where('tenant_id', $tenantId)->findOrFail($data['service_id']);
            $data['service_id'] = $service->id;
        }

        $hasEndsAt = array_key_exists('ends_at', $data) && ! empty($data['ends_at']);

        if (! empty($data['starts_at']) && ! $hasEndsAt) {
            $defaultDuration = $attributes['default_duration_minutes']
                ?? $service?->default_duration_minutes
                ?? 90;

            $data['ends_at'] = Carbon::parse($data['starts_at'])->addMinutes($defaultDuration);
        }

        return Gathering::create($data);
    }

    public function updateGathering(Gathering $gathering, array $attributes): Gathering
    {
        $payload = Arr::only($attributes, [
            'service_id',
            'name',
            'status',
            'starts_at',
            'ends_at',
            'location',
            'notes',
            'metadata',
        ]);

        if (array_key_exists('service_id', $payload) && ! empty($payload['service_id'])) {
            $service = Service::query()
                ->where('tenant_id', $gathering->tenant_id)
                ->findOrFail($payload['service_id']);

            $gathering->service()->associate($service);
        } elseif (array_key_exists('service_id', $payload) && empty($payload['service_id'])) {
            $gathering->service()->dissociate();
        }

        unset($payload['service_id']);

        $gathering->fill($payload);

        $gathering->save();

        return $gathering->refresh();
    }

    public function recordAttendance(Gathering $gathering, Member $member, array $attributes = []): AttendanceRecord
    {
        if ($gathering->tenant_id !== $member->tenant_id) {
            throw ValidationException::withMessages([
                'member_id' => 'Member does not belong to this tenant.',
            ]);
        }

        $payload = Arr::only($attributes, [
            'status',
            'check_in_method',
            'checked_in_at',
            'checked_out_at',
            'notes',
        ]);

        if (! isset($payload['checked_in_at']) && ($payload['status'] ?? 'present') === 'present') {
            $payload['checked_in_at'] = Carbon::now();
        }

        return AttendanceRecord::updateOrCreate(
            [
                'tenant_id' => $gathering->tenant_id,
                'gathering_id' => $gathering->id,
                'member_id' => $member->id,
            ],
            $payload
        );
    }

    public function bulkRecordAttendance(Gathering $gathering, array $members, string $status = 'present'): void
    {
        foreach ($members as $member) {
            if (! $member instanceof Member) {
                $member = Member::query()->where('tenant_id', $gathering->tenant_id)->findOrFail($member);
            }

            $this->recordAttendance($gathering, $member, ['status' => $status]);
        }
    }
}
