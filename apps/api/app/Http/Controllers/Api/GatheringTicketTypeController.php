<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gathering\StoreGatheringTicketTypeRequest;
use App\Http\Requests\Gathering\UpdateGatheringTicketTypeRequest;
use App\Http\Resources\GatheringTicketTypeResource;
use App\Models\Gathering;
use App\Models\GatheringTicketType;
use App\Services\GatheringRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatheringTicketTypeController extends Controller
{
    public function __construct(private readonly GatheringRegistrationService $service)
    {
        $this->middleware('feature:attendance');
        $this->middleware('can:gatherings.manage');
    }

    public function index(Request $request, Gathering $gathering): JsonResponse
    {
        $ticketTypes = $gathering->ticketTypes()->orderBy('name')->get();

        return GatheringTicketTypeResource::collection($ticketTypes)->response();
    }

    public function store(StoreGatheringTicketTypeRequest $request, Gathering $gathering): JsonResponse
    {
        $ticketType = $this->service->createTicketType($gathering, $request->validated());

        return GatheringTicketTypeResource::make($ticketType)->response()->setStatusCode(201);
    }

    public function show(Gathering $gathering, GatheringTicketType $ticketType): JsonResponse
    {
        $this->authorizeType($gathering, $ticketType);

        return GatheringTicketTypeResource::make($ticketType)->response();
    }

    public function update(UpdateGatheringTicketTypeRequest $request, Gathering $gathering, GatheringTicketType $ticketType): JsonResponse
    {
        $this->authorizeType($gathering, $ticketType);

        $ticketType = $this->service->updateTicketType($ticketType, $request->validated());

        return GatheringTicketTypeResource::make($ticketType)->response();
    }

    public function destroy(Gathering $gathering, GatheringTicketType $ticketType): JsonResponse
    {
        $this->authorizeType($gathering, $ticketType);

        $this->service->deleteTicketType($ticketType);

        return response()->json([], 204);
    }

    protected function authorizeType(Gathering $gathering, GatheringTicketType $ticketType): void
    {
        if ($ticketType->gathering_id !== $gathering->id) {
            abort(404);
        }
    }
}
