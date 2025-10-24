<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Mail\DonationReceiptMail;
use App\Models\Donation;
use App\Models\Member;
use App\Models\MemberContact;
use App\Models\Tenant;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DonationReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_generated_and_emailed_on_successful_donation(): void
    {
        Storage::fake('reports');
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($member)->state([
            'type' => 'email',
            'value' => 'donor@example.com',
        ])->create();

        /** @var FinanceService $service */
        $service = app(FinanceService::class);

        $donation = $service->recordDonation([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 125,
            'currency' => 'USD',
            'status' => 'succeeded',
            'items' => [
                ['amount' => 125],
            ],
        ]);

        $donation = $donation->fresh();

        $this->assertNotNull($donation->receipt_path);
        Storage::disk($donation->receipt_disk ?? 'reports')->assertExists($donation->receipt_path);
        $this->assertNotNull($donation->receipt_sent_at);

        Mail::assertSent(DonationReceiptMail::class, function (DonationReceiptMail $mail) use ($donation) {
            return $mail->donation->is($donation);
        });
    }

    public function test_receipt_sent_when_status_transitions_to_succeeded(): void
    {
        Storage::fake('reports');
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $member = Member::factory()->create(['tenant_id' => $tenant->id]);
        MemberContact::factory()->forMember($member)->state([
            'type' => 'email',
            'value' => 'transition@example.com',
        ])->create();

        /** @var FinanceService $service */
        $service = app(FinanceService::class);

        $donation = $service->recordDonation([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'amount' => 50,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        Mail::assertNothingSent();

        $service->updateDonation($donation, ['status' => 'succeeded']);

        $donation->refresh();
        $this->assertNotNull($donation->receipt_path);
        Storage::disk($donation->receipt_disk ?? 'reports')->assertExists($donation->receipt_path);
        $this->assertNotNull($donation->receipt_sent_at);

        Mail::assertSent(DonationReceiptMail::class);
    }
}
