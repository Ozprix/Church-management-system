<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MemberCustomField extends Model
{
    use HasFactory;
    use TenantScoped;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'data_type',
        'is_required',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $field): void {
            if (empty($field->slug)) {
                $field->slug = Str::slug($field->name);
            }
        });
    }

    public function values(): HasMany
    {
        return $this->hasMany(MemberCustomValue::class, 'field_id');
    }
}
