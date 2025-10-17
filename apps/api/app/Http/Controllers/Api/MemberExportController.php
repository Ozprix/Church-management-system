<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorizePermission('members.view');

        $filename = 'members-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $query = Member::query()
            ->with(['families'])
            ->when($request->query('status'), function ($builder, $status) {
                $builder->where('membership_status', $status);
            })
            ->when($request->query('search'), function ($builder, $search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('preferred_name', 'like', "%{$search}%")
                        ->orWhere('membership_stage', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'First name',
                'Last name',
                'Preferred name',
                'Status',
                'Stage',
                'Joined at',
                'Primary contact',
                'Primary contact value',
                'Family names',
            ]);

            $query->chunk(250, function ($members) use ($handle): void {
                foreach ($members as $member) {
                    $preferredContact = $member->preferred_contact;
                    $families = $member->families->pluck('family_name')->filter()->implode('; ');

                    fputcsv($handle, [
                        $member->first_name,
                        $member->last_name,
                        $member->preferred_name,
                        $member->membership_status,
                        $member->membership_stage,
                        optional($member->joined_at)->toDateString(),
                        $preferredContact['label'] ?? null,
                        $preferredContact['value'] ?? null,
                        $families,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
