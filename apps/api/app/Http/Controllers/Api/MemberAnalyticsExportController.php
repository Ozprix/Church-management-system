<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\Analytics\MemberAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberAnalyticsExportController extends Controller
{
    public function __construct(private readonly MemberAnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorizePermission('members.view');

        $filters = $this->analyticsService->parseFilters($request->query());
        $query = $this->analyticsService->applyFilters(Member::query()->with(['families', 'contacts']), $filters)
            ->orderBy('last_name')
            ->orderBy('first_name');

        $filename = 'member-analytics-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'First name',
                'Last name',
                'Status',
                'Stage',
                'Joined at',
                'Primary contact',
                'Primary contact value',
                'Has family',
            ]);

            $query->chunk(250, function ($members) use ($handle): void {
                foreach ($members as $member) {
                    $primary = $member->preferred_contact;
                    $hasFamily = $member->families->isNotEmpty() ? 'yes' : 'no';

                    fputcsv($handle, [
                        $member->first_name,
                        $member->last_name,
                        $member->membership_status,
                        $member->membership_stage,
                        optional($member->created_at)->toDateString(),
                        $primary['label'] ?? null,
                        $primary['value'] ?? null,
                        $hasFamily,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
