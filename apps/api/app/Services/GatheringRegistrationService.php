<?php

namespace App\Services;

use App\Models\Gathering;
use App\Models\GatheringRegistration;
use App\Models\GatheringTicketType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GatheringRegistrationService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function createTicketType(Gathering $gathering, array $attributes): GatheringTicketType
    {
        $payload = Arr::only($attributes, [
            'name',
            'capacity',
            'price',
            'currency',
            'sales_start_at',
            'sales_end_at',
            'metadata',
        ]);

        $payload['tenant_id'] = $gathering->tenant_id;
        $payload['currency'] = $payload['currency'] ?? $gathering->service?->default_currency ?? 'USD';

        return $gathering->ticketTypes()->create($payload)->fresh();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateTicketType(GatheringTicketType $ticketType, array $attributes): GatheringTicketType
    {
        $ticketType->fill(Arr::only($attributes, [
            'name',
            'capacity',
            'price',
            'currency',
            'sales_start_at',
            'sales_end_at',
            'metadata',
        ]));

        $ticketType->save();

        return $ticketType->refresh();
    }

    public function deleteTicketType(GatheringTicketType $ticketType): void
    {
        if ($ticketType->registrations()->exists()) {
            throw ValidationException::withMessages([
                'ticket_type' => ['Cannot delete ticket type with registrations.'],
            ]);
        }

        $ticketType->delete();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function createRegistration(Gathering $gathering, array $attributes): GatheringRegistration
    {
        return DB::transaction(function () use ($gathering, $attributes): GatheringRegistration {
            $ticketType = null;
            if (! empty($attributes['ticket_type_id'])) {
                $ticketType = $gathering->ticketTypes()
                    ->where('id', $attributes['ticket_type_id'])
                    ->firstOrFail();
            }

            $quantity = (int) ($attributes['quantity'] ?? 1);
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'quantity' => ['Quantity must be at least 1.'],
                ]);
            }

            if ($ticketType) {
                $this->ensureCapacity($ticketType, $quantity);
            }

            $payload = Arr::only($attributes, [
                'ticket_type_id',
                'member_id',
                'status',
                'quantity',
                'name',
                'email',
                'phone',
                'amount',
                'currency',
                'checked_in_at',
                'notes',
                'metadata',
            ]);

            $payload['status'] = $payload['status'] ?? GatheringRegistration::STATUS_PENDING;
            $payload['currency'] = $payload['currency'] ?? $ticketType?->currency ?? 'USD';
            $payload['tenant_id'] = $gathering->tenant_id;
            $payload['amount'] = $payload['amount'] ?? $ticketType?->price ?? 0;

            $registration = $gathering->registrations()->create($payload);

            return $registration->fresh(['ticketType', 'member']);
        });
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateRegistration(GatheringRegistration $registration, array $attributes): GatheringRegistration
    {
        return DB::transaction(function () use ($registration, $attributes): GatheringRegistration {
            $payload = Arr::only($attributes, [
                'status',
                'quantity',
                'ticket_type_id',
                'amount',
                'currency',
                'checked_in_at',
                'notes',
                'metadata',
            ]);

            if (array_key_exists('ticket_type_id', $payload) && $payload['ticket_type_id']) {
                $ticketType = GatheringTicketType::query()
                    ->where('tenant_id', $registration->tenant_id)
                    ->where('gathering_id', $registration->gathering_id)
                    ->findOrFail($payload['ticket_type_id']);
            } else {
                $ticketType = $registration->ticketType;
            }

            if (array_key_exists('quantity', $payload) || array_key_exists('ticket_type_id', $payload)) {
                $quantity = (int) ($payload['quantity'] ?? $registration->quantity);
                $this->ensureCapacity($ticketType, $quantity, $registration);
            }

            $registration->fill($payload);
            $registration->save();

            return $registration->fresh(['ticketType', 'member']);
        });
    }

    public function checkIn(GatheringRegistration $registration): GatheringRegistration
    {
        $registration->forceFill([
            'status' => GatheringRegistration::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ])->save();

        return $registration->fresh();
    }

    protected function ensureCapacity(?GatheringTicketType $ticketType, int $quantity, ?GatheringRegistration $ignore = null): void
    {
        if (! $ticketType || $ticketType->capacity === null) {
            return;
        }

        $used = $ticketType->registrations()
            ->whereIn('status', [
                GatheringRegistration::STATUS_PENDING,
                GatheringRegistration::STATUS_CONFIRMED,
                GatheringRegistration::STATUS_CHECKED_IN,
            ])
            ->when($ignore, fn ($query) => $query->where('id', '!=', $ignore->id))
            ->sum('quantity');

        if ($used + $quantity > $ticketType->capacity) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Only %d tickets remaining for %s.',
                    max($ticketType->capacity - $used, 0),
                    $ticketType->name
                ),
            ]);
        }
    }
}
