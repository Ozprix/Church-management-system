<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FamilyExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorizePermission('members.view');

        $filename = 'families-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $query = Family::query()
            ->withCount('members')
            ->with(['members' => function ($relation) {
                $relation->select(['members.id', 'members.first_name', 'members.last_name']);
            }])
            ->when($request->query('search'), function ($builder, $search) {
                $builder->where('family_name', 'like', "%{$search}%");
            })
            ->orderBy('family_name');

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Family name',
                'Members count',
                'Primary contacts',
                'Emergency contacts',
                'Has primary contact',
                'Has emergency contact',
                'City',
                'State/Region',
                'Created at',
            ]);

            $query->chunk(200, function ($families) use ($handle): void {
                foreach ($families as $family) {
                    $primaryContacts = $family->members
                        ->filter(fn ($member) => (bool) ($member->pivot->is_primary_contact ?? false))
                        ->map(fn ($member) => $member->first_name . ' ' . $member->last_name)
                        ->implode('; ');

                    $emergencyContacts = $family->members
                        ->filter(fn ($member) => (bool) ($member->pivot->is_emergency_contact ?? false))
                        ->map(fn ($member) => $member->first_name . ' ' . $member->last_name)
                        ->implode('; ');

                    $hasPrimary = $family->members->contains(fn ($member) => (bool) ($member->pivot->is_primary_contact ?? false));
                    $hasEmergency = $family->members->contains(fn ($member) => (bool) ($member->pivot->is_emergency_contact ?? false));

                    $city = $family->address['city'] ?? null;
                    $state = $family->address['state'] ?? null;

                    fputcsv($handle, [
                        $family->family_name,
                        $family->members_count,
                        $primaryContacts,
                        $emergencyContacts,
                        $hasPrimary ? 'yes' : 'no',
                        $hasEmergency ? 'yes' : 'no',
                        $city,
                        $state,
                        optional($family->created_at)->toDateString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
