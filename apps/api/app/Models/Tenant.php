<?php

namespace App\Models;

use App\Services\Finance\ChartOfAccountsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'status',
        'plan',
        'timezone',
        'locale',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }

            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });

        static::created(function (self $tenant) {
            app(ChartOfAccountsService::class)->ensureDefaultsForTenant($tenant);
        });
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function gatherings(): HasMany
    {
        return $this->hasMany(Gathering::class);
    }

    public function notificationTemplates(): HasMany
    {
        return $this->hasMany(NotificationTemplate::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class);
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedgerEntry::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }

    public function planSubscriptions(): HasMany
    {
        return $this->hasMany(TenantPlan::class);
    }

    public function planUsages(): HasMany
    {
        return $this->hasMany(TenantPlanUsage::class);
    }

    public function membershipProcesses(): HasMany
    {
        return $this->hasMany(MembershipProcess::class);
    }

    public function volunteerSignups(): HasMany
    {
        return $this->hasMany(VolunteerSignup::class);
    }

    public function volunteerHours(): HasMany
    {
        return $this->hasMany(VolunteerHour::class);
    }

    public function notificationRules(): HasMany
    {
        return $this->hasMany(NotificationRule::class);
    }

    public function notificationMetrics(): HasMany
    {
        return $this->hasMany(NotificationMetric::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
