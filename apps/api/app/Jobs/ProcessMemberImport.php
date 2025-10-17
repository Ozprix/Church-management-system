<?php

namespace App\Jobs;

use App\Models\MemberImport;
use App\Models\Tenant;
use App\Services\MemberService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessMemberImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly MemberImport $memberImport)
    {
        $this->queue = 'imports';
    }

    public function handle(MemberService $memberService, TenantManager $tenantManager): void
    {
        $import = $this->memberImport->fresh();

        if (! $import) {
            return;
        }

        $tenant = Tenant::query()->find($import->tenant_id);

        if (! $tenant) {
            $import->update([
                'status' => MemberImport::STATUS_FAILED,
                'errors' => ['Tenant not found.'],
            ]);

            return;
        }

        $tenantManager->setTenant($tenant);

        $import->update([
            'status' => MemberImport::STATUS_PROCESSING,
            'errors' => null,
            'processed_rows' => 0,
            'failed_rows' => 0,
        ]);

        $path = $import->stored_path;

        if (! Storage::exists($path)) {
            $import->update([
                'status' => MemberImport::STATUS_FAILED,
                'errors' => ['Uploaded file not found.'],
            ]);

            return;
        }

        $fullPath = Storage::path($path);
        $handle = fopen($fullPath, 'rb');

        if (! $handle) {
            $import->update([
                'status' => MemberImport::STATUS_FAILED,
                'errors' => ['Unable to read uploaded file.'],
            ]);

            return;
        }

        $headers = null;
        $rows = [];
        $errors = [];
        $processed = 0;
        $failed = 0;

        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map(
                    fn ($header) => strtolower(trim((string) $header)),
                    $data
                );
                continue;
            }

            if (count(array_filter($data)) === 0) {
                continue;
            }

            $row = array_combine($headers, $data);

            if ($row === false) {
                $failed++;
                $errors[] = ['row' => $processed + $failed, 'error' => 'Malformed row.'];
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        $import->update(['total_rows' => count($rows)]);

        $chunks = collect($rows)->chunk(50);

        foreach ($chunks as $chunkIndex => $chunk) {
            $payloads = [];

            foreach ($chunk as $rowIndex => $row) {
                $payload = $this->mapRowToPayload($row);

                if (empty($payload['first_name']) || empty($payload['last_name'])) {
                    $failed++;
                    $errors[] = [
                        'row' => ($chunkIndex * 50) + $rowIndex + 1,
                        'error' => 'First name and last name are required.',
                    ];
                    continue;
                }

                $payloads[] = $payload;
            }

            if (empty($payloads)) {
                continue;
            }

            try {
                $created = $memberService->bulkImport($payloads);
                $processed += $created->count();
            } catch (\Throwable $exception) {
                Log::error('Member import chunk failed', [
                    'import_id' => $import->id,
                    'tenant_id' => $import->tenant_id,
                    'error' => $exception->getMessage(),
                ]);

                $failed += count($payloads);
                $errors[] = [
                    'row' => ($chunkIndex * 50) + 1,
                    'error' => 'Chunk failed: ' . $exception->getMessage(),
                ];
            }
        }

        $status = $failed === 0 ? MemberImport::STATUS_COMPLETED : MemberImport::STATUS_FAILED;

        $import->update([
            'status' => $status,
            'processed_rows' => $processed,
            'failed_rows' => $failed,
            'errors' => $errors ?: null,
            'completed_at' => now(),
        ]);

        $tenantManager->forgetTenant();
    }

    protected function mapRowToPayload(array $row): array
    {
        $payload = [
            'first_name' => Arr::get($row, 'first_name'),
            'last_name' => Arr::get($row, 'last_name'),
            'membership_status' => Arr::get($row, 'membership_status', 'prospect'),
        ];

        $email = Arr::get($row, 'email');
        if ($email) {
            $payload['contacts'] = [
                [
                    'type' => 'email',
                    'value' => $email,
                    'is_primary' => true,
                ],
            ];
        }

        return $payload;
    }
}
