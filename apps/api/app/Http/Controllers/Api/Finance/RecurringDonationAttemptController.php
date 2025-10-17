<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\RecurringDonationAttemptResource;
use App\Models\RecurringDonationAttempt;
use App\Models\RecurringDonationSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringDonationAttemptController extends Controller
{
    public function __construct()
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.manage_recurring');
    }

    public function index(Request $request, RecurringDonationSchedule $schedule): JsonResponse
    {
        $attempts = RecurringDonationAttempt::query()
            ->where('schedule_id', $schedule->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return RecurringDonationAttemptResource::collection($attempts)->response();
    }
}
