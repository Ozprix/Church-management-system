<?php

namespace App\Mail;

use App\Models\Donation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DonationReceiptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Donation $donation,
        private readonly string $pdfData,
        private readonly string $filename
    ) {}

    public function build(): self
    {
        $donation = $this->donation->loadMissing(['tenant', 'member', 'items.fund']);
        $tenant = $donation->tenant;
        $member = $donation->member;

        $subject = sprintf('Receipt for your gift%s', $tenant ? ' to ' . $tenant->name : '');

        return $this
            ->subject($subject)
            ->view('emails.donations.receipt', [
                'donation' => $donation,
                'tenant' => $tenant,
                'member' => $member,
            ])
            ->attachData($this->pdfData, $this->filename, ['mime' => 'application/pdf']);
    }
}
