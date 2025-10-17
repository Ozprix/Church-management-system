<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\Recurring\StoreRecurringDonationScheduleRequest;
use App\Http\Requests\Finance\Recurring\UpdateRecurringDonationScheduleRequest;
use App\Http\Resources\Finance\RecurringDonationScheduleResource;
use App\Models\RecurringDonationSchedule;
use App\Services\RecurringDonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringDonationScheduleController extends Controller
{
    public function __construct(private readonly RecurringDonationService $service)
    {
        $this->middleware('feature:finance');
        $this->middleware('can:finance.manage_recurring');
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $schedules = RecurringDonationSchedule::query()
            ->with(['member', 'paymentMethod'])
            ->when($tenant, fn ($query) => $query->where('tenant_id', $tenant->id))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return RecurringDonationScheduleResource::collection($schedules)->response();
    }

    public function store(StoreRecurringDonationScheduleRequest $request): JsonResponse
    {
        $schedule = $this->service->createSchedule($request->validated());

        return RecurringDonationScheduleResource::make($schedule->load(['member', 'paymentMethod']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RecurringDonationSchedule $recurringDonationSchedule): RecurringDonationScheduleResource
    {
        return RecurringDonationScheduleResource::make($recurringDonationSchedule->load(['member', 'paymentMethod', 'attempts']));
    }

    public function update(
        UpdateRecurringDonationScheduleRequest $request,
        RecurringDonationSchedule $recurringDonationSchedule
    ): RecurringDonationScheduleResource {
        $schedule = $this->service->updateSchedule($recurringDonationSchedule, $request->validated());

        return RecurringDonationScheduleResource::make($schedule->load(['member', 'paymentMethod']));
    }

    public function destroy(RecurringDonationSchedule $recurringDonationSchedule): JsonResponse
    {
        $recurringDonationSchedule->delete();

        return response()->json([], 204);
    }
}
