<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ThrottleLimitExceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $key,
        public readonly string $route,
        public readonly ?int $userId,
        public readonly ?string $ip
    ) {
    }
}
