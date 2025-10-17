<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Donation;
use App\Models\DonationItem;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DonationItem>
 */
class DonationItemFactory extends Factory
{
    protected $model = DonationItem::class;

    public function definition(): array
    {
        return [
            'donation_id' => Donation::factory(),
            'fund_id' => Fund::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
