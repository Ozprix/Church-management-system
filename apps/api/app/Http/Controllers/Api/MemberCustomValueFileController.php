<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberCustomValue;
use Illuminate\Support\Facades\Storage;

class MemberCustomValueFileController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:members');
        $this->middleware('can:members.view');
    }

    public function __invoke(MemberCustomValue $memberCustomValue)
    {
        if (!in_array(optional($memberCustomValue->field)->data_type, ['file', 'signature'], true)) {
            abort(404);
        }

        if (!$memberCustomValue->value_file_disk || !$memberCustomValue->value_file_path) {
            abort(404);
        }

        $disk = Storage::disk($memberCustomValue->value_file_disk);

        if (!$disk->exists($memberCustomValue->value_file_path)) {
            abort(404);
        }

        $path = $memberCustomValue->value_file_path;
        $filename = $memberCustomValue->value_file_name ?? basename($path);
        $contentType = $memberCustomValue->value_file_mime ?? 'application/octet-stream';

        return response()->streamDownload(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);

            if ($stream === false) {
                return;
            }

            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }
}
