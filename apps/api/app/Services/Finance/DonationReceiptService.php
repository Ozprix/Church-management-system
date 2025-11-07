<?php

namespace App\Services\Finance;

use App\Mail\DonationReceiptMail;
use App\Models\Donation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class DonationReceiptService
{
    public function __construct()
    {
    }

    public function ensureReceipt(Donation $donation, bool $force = false): Donation
    {
        if (! $force && $donation->receipt_path && Storage::disk($donation->receipt_disk ?? $this->defaultDisk())->exists($donation->receipt_path)) {
            return $donation;
        }

        return $this->generateReceipt($donation);
    }

    public function sendReceipt(Donation $donation): bool
    {
        $donation = $this->ensureReceipt($donation);

        $member = $donation->member?->loadMissing('contacts');
        $email = $member?->preferredContact()?->value;

        if (! $email) {
            $email = $member?->contacts?->firstWhere('type', 'email')?->value;
        }

        if (! $email) {
            return false;
        }

        $disk = $donation->receipt_disk ?? $this->defaultDisk();
        if (! $donation->receipt_path || ! Storage::disk($disk)->exists($donation->receipt_path)) {
            return false;
        }

        $pdfData = Storage::disk($disk)->get($donation->receipt_path);
        $filename = basename($donation->receipt_path);

        Mail::to($email)->send(new DonationReceiptMail($donation->fresh(['member', 'tenant', 'items.fund']), $pdfData, $filename));

        $donation->forceFill([
            'receipt_sent_at' => Carbon::now(),
        ])->save();

        return true;
    }

    protected function generateReceipt(Donation $donation): Donation
    {
        $donation->loadMissing(['tenant', 'member', 'items.fund']);

        $config = config('reports.pdf_config', [
            'tempDir' => storage_path('app/reports/tmp'),
        ]);

        if (! is_dir($config['tempDir'])) {
            @mkdir($config['tempDir'], 0755, true);
        }

        $mpdf = new Mpdf($config);

        $html = view('receipts.donation', [
            'donation' => $donation,
            'tenant' => $donation->tenant,
            'member' => $donation->member,
            'items' => $donation->items,
        ])->render();

        $mpdf->WriteHTML($html);

        $disk = $this->defaultDisk();
        $directory = trim(config('reports.directory', 'finance'), '/') . '/receipts';
        $filename = sprintf('donation-receipt-%s.pdf', Str::uuid()->toString());
        $path = $directory . '/' . $filename;

        Storage::disk($disk)->put($path, $mpdf->OutputBinaryData());

        $donation->forceFill([
            'receipt_disk' => $disk,
            'receipt_path' => $path,
            'receipt_generated_at' => Carbon::now(),
        ])->save();

        return $donation;
    }

    protected function defaultDisk(): string
    {
        return config('reports.disk', 'reports');
    }
}
