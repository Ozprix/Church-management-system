<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gathering;
use App\Services\Attendance\AttendanceExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AttendanceExportController extends Controller
{
    public function __construct(private readonly AttendanceExportService $exports)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:attendance.view');
    }

    public function show(Request $request, Gathering $gathering)
    {
        $format = strtolower((string) $request->query('format', 'csv'));

        if ($format === 'pdf') {
            $pdf = $this->exports->pdf($gathering);
            $filename = $this->exports->filenameForGathering($gathering, 'pdf');

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $filename = $this->exports->filenameForGathering($gathering, 'csv');
        $service = $this->exports;

        return response()->stream(function () use ($service, $gathering): void {
            $handle = fopen('php://output', 'wb');
            $service->streamCsv($gathering, $handle);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function bulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gathering_ids' => ['required', 'array', 'min:1', 'max:25'],
            'gathering_ids.*' => ['integer', 'exists:gatherings,id'],
            'format' => ['nullable', 'in:csv,pdf'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('Invalid export request.'),
                'errors' => $validator->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $format = $request->input('format', 'csv');
        $gatherings = Gathering::query()
            ->whereIn('id', $request->input('gathering_ids', []))
            ->orderBy('starts_at')
            ->get();

        if ($gatherings->isEmpty()) {
            return response()->json([
                'message' => __('No gatherings matched the provided identifiers.'),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tempDir = storage_path('app/tmp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = tempnam($tempDir, 'attendance-export-');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'message' => __('Unable to create export archive.'),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        foreach ($gatherings as $gathering) {
            if ($format === 'pdf') {
                $content = $this->exports->pdf($gathering);
                $filename = $this->exports->filenameForGathering($gathering, 'pdf');
            } else {
                $content = $this->exports->csvString($gathering);
                $filename = $this->exports->filenameForGathering($gathering, 'csv');
            }

            $zip->addFromString($filename, $content);
        }

        $zip->close();

        $downloadName = sprintf('attendance-export-%s.zip', now()->format('Ymd_His'));
        $headers = [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
        ];

        $contents = file_get_contents($zipPath) ?: '';
        unlink($zipPath);

        return response($contents, 200, $headers);
    }
}
