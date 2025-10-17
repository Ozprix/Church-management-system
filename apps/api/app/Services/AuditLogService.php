<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class AuditLogService
{
    public function record(array $attributes): AuditLog
    {
        return AuditLog::create([
            'tenant_id' => Arr::get($attributes, 'tenant_id'),
            'user_id' => Arr::get($attributes, 'user_id'),
            'action' => Arr::get($attributes, 'action'),
            'auditable_type' => Arr::get($attributes, 'auditable_type'),
            'auditable_id' => Arr::get($attributes, 'auditable_id'),
            'payload' => $this->sanitizePayload(Arr::get($attributes, 'payload')),
            'ip_address' => Arr::get($attributes, 'ip_address'),
            'user_agent' => Arr::get($attributes, 'user_agent'),
            'occurred_at' => Arr::get($attributes, 'occurred_at', now()),
        ]);
    }

    protected function sanitizePayload(mixed $payload): mixed
    {
        if (is_null($payload)) {
            return null;
        }

        if ($payload instanceof UploadedFile) {
            return [
                'name' => $payload->getClientOriginalName(),
                'mime' => $payload->getClientMimeType(),
                'size' => $payload->getSize(),
            ];
        }

        if ($payload instanceof \DateTimeInterface) {
            return $payload->format(\DateTimeInterface::ATOM);
        }

        if ($payload instanceof \JsonSerializable) {
            return $payload->jsonSerialize();
        }

        if ($payload instanceof \UnitEnum) {
            return $payload->value ?? $payload->name;
        }

        if (is_array($payload)) {
            return array_map(fn ($value) => $this->sanitizePayload($value), $payload);
        }

        if (is_object($payload)) {
            return method_exists($payload, '__toString') ? (string) $payload : get_class($payload);
        }

        return $payload;
    }
}
