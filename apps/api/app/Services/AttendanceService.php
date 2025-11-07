<?php

namespace App\Services;

use App\Events\AttendanceRecorded;
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

        $this->ensureNoConflicts(
            tenantId: $tenantId,
            startsAt: Carbon::parse($data['starts_at']),
            endsAt: Carbon::parse($data['ends_at']),
            location: $data['location'] ?? null,
            serviceId: $data['service_id'] ?? null,
        );

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

        $startsAt = array_key_exists('starts_at', $payload)
            ? Carbon::parse($payload['starts_at'])
            : $gathering->starts_at;

        $endsAt = array_key_exists('ends_at', $payload)
            ? Carbon::parse($payload['ends_at'])
            : $gathering->ends_at;

        $location = array_key_exists('location', $payload)
            ? $payload['location']
            : $gathering->location;

        $serviceId = $gathering->service?->id;
        if (array_key_exists('service_id', $attributes)) {
            $serviceId = $attributes['service_id'] ?? null;
        }

        $this->ensureNoConflicts(
            tenantId: $gathering->tenant_id,
            startsAt: $startsAt,
            endsAt: $endsAt ?? $startsAt->copy()->addMinutes(90),
            location: $location,
            serviceId: $serviceId,
            ignoreGathering: $gathering,
        );

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

        $record = AttendanceRecord::updateOrCreate(
            [
                'tenant_id' => $gathering->tenant_id,
                'gathering_id' => $gathering->id,
                'member_id' => $member->id,
            ],
            $payload
        );

        event(new AttendanceRecorded($record->fresh(['member', 'gathering.service'])));

        return $record;
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

    protected function ensureNoConflicts(
        int $tenantId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?string $location,
        ?int $serviceId,
        ?Gathering $ignoreGathering = null
    ): void {
        $query = Gathering::query()
            ->where('tenant_id', $tenantId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($location) {
            $query->where(function ($builder) use ($location, $serviceId): void {
                $builder->where(function ($inner) use ($location): void {
                    $inner->whereNotNull('location')
                        ->where('location', $location);
                });

                if ($serviceId) {
                    $builder->orWhere('service_id', $serviceId);
                }
            });
        } elseif ($serviceId) {
            $query->where('service_id', $serviceId);
        }

        if ($ignoreGathering) {
            $query->where('id', '!=', $ignoreGathering->id);
        }

        $conflict = $query->first();

        if ($conflict) {
            throw ValidationException::withMessages([
                'starts_at' => sprintf(
                    'Conflicts with existing gathering "%s" from %s to %s.',
                    $conflict->name,
                    $conflict->starts_at?->toDayDateTimeString(),
                    $conflict->ends_at?->toDayDateTimeString()
                ),
            ]);
        }
    }
}
