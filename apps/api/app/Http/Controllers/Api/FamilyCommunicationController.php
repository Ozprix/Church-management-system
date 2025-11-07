<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Family\SendFamilyCommunicationRequest;
use App\Models\Family;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class FamilyCommunicationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
        $this->middleware('feature:members');
        $this->middleware('can:families.manage');
    }

    public function store(SendFamilyCommunicationRequest $request, Family $family): JsonResponse
    {
        $family->load(['members.contacts']);

        $target = $request->input('target', 'primary');
        $channel = $request->input('channel');
        $subject = $request->input('subject');
        $body = $request->input('body');

        $recipients = match ($target) {
            'emergency' => $family->members->filter(fn ($member) => (bool) ($member->pivot->is_emergency_contact ?? false)),
            'all' => $family->members,
            default => $family->members->filter(fn ($member) => (bool) ($member->pivot->is_primary_contact ?? false)),
        };

        if ($recipients->isEmpty()) {
            return response()->json([
                'message' => 'No recipients available for the selected household target.',
            ], 422);
        }

        $queued = 0;

        foreach ($recipients as $member) {
            $payload = [
                'tenant_id' => $family->tenant_id,
                'member_id' => $member->id,
                'channel' => $channel,
                'body' => $body,
                'payload' => [
                    'family_id' => $family->id,
                    'family_name' => $family->family_name,
                    'target' => $target,
                ],
            ];

            if ($channel === 'email' && $subject) {
                $payload['subject'] = $subject;
            }

            $this->notificationService->queue($payload);
            ++$queued;
        }

        return response()->json([
            'data' => [
                'queued' => $queued,
            ],
        ]);
    }
}
