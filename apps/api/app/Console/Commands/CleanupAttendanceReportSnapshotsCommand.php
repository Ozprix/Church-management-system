<?php

namespace App\Console\Commands;

use App\Models\AttendanceAnalyticsReportSnapshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupAttendanceReportSnapshotsCommand extends Command
{
    protected $signature = 'attendance:cleanup-snapshots {--dry-run : List expired snapshots without deleting files}';

    protected $description = 'Remove expired attendance report snapshots and delete their stored files.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $expiredSnapshots = AttendanceAnalyticsReportSnapshot::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->limit(500)
            ->get();

        if ($expiredSnapshots->isEmpty()) {
            $this->info('No expired snapshots found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d expired snapshots.', $expiredSnapshots->count()));

        $deleted = 0;
        foreach ($expiredSnapshots as $snapshot) {
            $disk = $snapshot->disk ?? config('attendance_reports.disk', 'reports');
            $path = $snapshot->file_path;

            if ($dryRun) {
                $this->line(sprintf('[Dry run] Would delete %s:%s', $disk, $path));
                continue;
            }

            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
                $this->line(sprintf('Deleted file %s:%s', $disk, $path));
            }

            $snapshot->delete();
            $deleted++;
        }

        if (! $dryRun) {
            $this->info(sprintf('Removed %d expired snapshots.', $deleted));
        }

        return self::SUCCESS;
    }
}

