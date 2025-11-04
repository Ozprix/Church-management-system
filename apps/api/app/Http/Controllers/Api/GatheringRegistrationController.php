<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gathering\StoreGatheringRegistrationRequest;
use App\Http\Requests\Gathering\UpdateGatheringRegistrationRequest;
use App\Http\Resources\GatheringRegistrationResource;
use App\Models\Gathering;
use App\Models\GatheringRegistration;
use App\Services\GatheringRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatheringRegistrationController extends Controller
{
    public function __construct(private readonly GatheringRegistrationService $service)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:gatherings.manage')->only(['store', 'update', 'destroy', 'checkIn']);
        $this->middleware('can:attendance.view');
    }

    public function index(Request $request, Gathering $gathering): JsonResponse
    {
        $registrations = $gathering->registrations()
            ->with(['ticketType', 'member'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return GatheringRegistrationResource::collection($registrations)->response();
    }

    public function store(StoreGatheringRegistrationRequest $request, Gathering $gathering): JsonResponse
    {
        $registration = $this->service->createRegistration($gathering, $request->validated());

        return GatheringRegistrationResource::make($registration)->response()->setStatusCode(201);
    }

    public function show(Gathering $gathering, GatheringRegistration $registration): JsonResponse
    {
        $this->authorizeRegistration($gathering, $registration);

        return GatheringRegistrationResource::make($registration->load(['ticketType', 'member']))->response();
    }

    public function update(UpdateGatheringRegistrationRequest $request, Gathering $gathering, GatheringRegistration $registration): JsonResponse
    {
        $this->authorizeRegistration($gathering, $registration);

        $registration = $this->service->updateRegistration($registration, $request->validated());

        return GatheringRegistrationResource::make($registration)->response();
    }

    public function destroy(Gathering $gathering, GatheringRegistration $registration): JsonResponse
    {
        $this->authorizeRegistration($gathering, $registration);

        $registration->delete();

        return response()->json([], 204);
    }

    public function checkIn(Gathering $gathering, GatheringRegistration $registration): JsonResponse
    {
        $this->authorizeRegistration($gathering, $registration);

        $registration = $this->service->checkIn($registration);

        return GatheringRegistrationResource::make($registration)->response();
    }

    protected function authorizeRegistration(Gathering $gathering, GatheringRegistration $registration): void
    {
        if ($registration->gathering_id !== $gathering->id) {
            abort(404);
        }
    }
}
