<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use App\Models\VisitorWorkflow;
use App\Services\NotificationService;
use App\Services\VisitorAutomationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'first_name',
        'last_name',
        'middle_name',
        'preferred_name',
        'gender',
        'dob',
        'marital_status',
        'membership_status',
        'membership_stage',
        'joined_at',
        'photo_path',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dob' => 'date',
        'joined_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $member): void {
            if (empty($member->uuid)) {
                $member->uuid = (string) Str::uuid();
            }
        });

        static::created(function (self $member): void {
            if ($member->membership_status === 'visitor') {
                self::startVisitorWorkflow($member);
            }
        });

        static::updated(function (self $member): void {
            if ($member->isDirty('membership_stage')) {
                self::notifyStageChange($member, $member->getOriginal('membership_stage'));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(MemberContact::class);
    }

    public function customValues(): HasMany
    {
        return $this->hasMany(MemberCustomValue::class)->with('field');
    }

    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class, 'family_members')
            ->withPivot(['relationship', 'is_primary_contact', 'is_emergency_contact'])
            ->withTimestamps();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function gatherings(): BelongsToMany
    {
        return $this->belongsToMany(Gathering::class, 'attendance_records')
            ->withPivot(['status', 'checked_in_at', 'checked_out_at', 'check_in_method'])
            ->withTimestamps();
    }

    public function volunteerAssignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class);
    }

    public function volunteerAvailability(): HasMany
    {
        return $this->hasMany(VolunteerAvailability::class, 'member_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function processRuns(): HasMany
    {
        return $this->hasMany(MemberProcessRun::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('membership_status', $status);
    }

    public function preferredContact(): ?MemberContact
    {
        return $this->contacts->firstWhere('is_primary', true) ?? $this->contacts->first();
    }

    protected static function startVisitorWorkflow(self $member): void
    {
        try {
            $workflow = VisitorWorkflow::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->first();

            if (! $workflow) {
                return;
            }

            app(VisitorAutomationService::class)->startFollowup($member, $workflow);
        } catch (\Throwable $exception) {
            Log::error('Unable to start visitor workflow', [
                'member_id' => $member->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected static function notifyStageChange(self $member, ?string $previousStage): void
    {
        $tenant = $member->tenant;
        if (! $tenant) {
            return;
        }

        $recipient = $tenant->users()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['visitor_manager', 'admin']))
            ->value('email');

        if (! $recipient) {
            return;
        }

        $subject = 'Visitor stage update: ' . trim($member->first_name . ' ' . $member->last_name);
        $body = sprintf(
            "Stage changed from %s to %s for %s.",
            $previousStage ?? 'unknown',
            $member->membership_stage ?? 'unspecified',
            trim($member->first_name . ' ' . $member->last_name)
        );

        app(NotificationService::class)->queue([
            'tenant_id' => $tenant->id,
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
        ]);
    }
}
