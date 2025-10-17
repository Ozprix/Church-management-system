<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MemberCustomValue extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'field_id',
        'value_string',
        'value_text',
        'value_number',
        'value_date',
        'value_boolean',
        'value_json',
        'value_file_disk',
        'value_file_path',
        'value_file_name',
        'value_file_mime',
        'value_file_size',
    ];

    protected $casts = [
        'value_date' => 'date',
        'value_number' => 'decimal:2',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
        'value_file_size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $value): void {
            $value->deleteStoredFile();
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(MemberCustomField::class, 'field_id');
    }

    public function deleteStoredFile(): void
    {
        if (!$this->value_file_disk || !$this->value_file_path) {
            return;
        }

        Storage::disk($this->value_file_disk)->delete($this->value_file_path);

        $this->value_file_disk = null;
        $this->value_file_path = null;
        $this->value_file_name = null;
        $this->value_file_mime = null;
        $this->value_file_size = null;
        $this->value_json = null;
    }

    public function hasStoredFile(): bool
    {
        return !empty($this->value_file_disk) && !empty($this->value_file_path);
    }
}
