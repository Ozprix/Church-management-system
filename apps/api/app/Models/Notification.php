<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Notification extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'notification_template_id',
        'member_id',
        'channel',
        'recipient',
        'subject',
        'body',
        'payload',
        'status',
        'provider',
        'provider_message_id',
        'scheduled_for',
        'sent_at',
        'error_message',
        'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function markSending(): void
    {
        $this->forceFill([
            'status' => 'sending',
            'attempts' => $this->attempts + 1,
        ])->save();
    }

    public function markSent(string $provider, ?string $providerMessageId = null): void
    {
        $this->forceFill([
            'status' => 'sent',
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'sent_at' => Carbon::now(),
            'error_message' => null,
        ])->save();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->forceFill([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ])->save();
    }

    public function isDue(): bool
    {
        return ! $this->scheduled_for || $this->scheduled_for->isPast();
    }
}
