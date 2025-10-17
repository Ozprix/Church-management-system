<?php

namespace App\Services\CustomFields;

use App\Models\MemberCustomField;
use App\Models\MemberCustomValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomFieldFileService
{
    public function store(MemberCustomField $field, UploadedFile $file, int $tenantId): array
    {
        $disk = config('custom-fields.disk');
        $directory = trim((string) config('custom-fields.upload_directory', 'custom-fields'), '/');
        $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $pathPrefix = sprintf('%s/%s/%s', $directory, $tenantId, $field->id);

        $path = Storage::disk($disk)->putFileAs(
            $pathPrefix,
            $file,
            $filename,
            [
                'visibility' => $this->determineVisibility($disk),
            ]
        );

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ];
    }

    public function delete(MemberCustomValue $value): void
    {
        if (!$value->value_file_disk || !$value->value_file_path) {
            return;
        }

        Storage::disk($value->value_file_disk)->delete($value->value_file_path);
    }

    public function temporaryUrl(MemberCustomValue $value): ?string
    {
        if (!$value->value_file_disk || !$value->value_file_path) {
            return null;
        }

        return $this->temporaryUrlFromMetadata([
            'disk' => $value->value_file_disk,
            'path' => $value->value_file_path,
        ]) ?? route('member-custom-values.file.download', $value);
    }

    public function metadataFromArray(array $metadata): array
    {
        return [
            'value_string' => null,
            'value_text' => null,
            'value_number' => null,
            'value_date' => null,
            'value_boolean' => null,
            'value_json' => $metadata,
            'value_file_disk' => Arr::get($metadata, 'disk'),
            'value_file_path' => Arr::get($metadata, 'path'),
            'value_file_name' => Arr::get($metadata, 'name'),
            'value_file_mime' => Arr::get($metadata, 'mime'),
            'value_file_size' => Arr::get($metadata, 'size'),
        ];
    }

    protected function determineVisibility(string $disk): ?string
    {
        $config = config("filesystems.disks.{$disk}");

        if (is_array($config) && array_key_exists('visibility', $config)) {
            return $config['visibility'];
        }

        return null;
    }

    public function temporaryUrlFromMetadata(?array $metadata): ?string
    {
        $diskName = Arr::get($metadata, 'disk');
        $path = Arr::get($metadata, 'path');

        if (!$diskName || !$path) {
            return null;
        }

        $disk = Storage::disk($diskName);
        $expirationMinutes = (int) config('custom-fields.url_expires', 5);

        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($path, now()->addMinutes($expirationMinutes));
        }

        return null;
    }
}
