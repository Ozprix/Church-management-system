<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Services\Analytics\FamilyAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FamilyAnalyticsExportController extends Controller
{
    public function __construct(private readonly FamilyAnalyticsService $analyticsService)
    {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorizePermission('families.view');

        $filters = $this->analyticsService->parseFilters($request->query());
        $query = $this->analyticsService
            ->applyFilters(Family::query()->with(['familyMembers', 'members'])->withCount('members'), $filters)
            ->orderBy('family_name');

        $filename = 'family-analytics-' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Family name',
                'Members',
                'Has primary contact',
                'Created at',
            ]);

            $query->chunk(250, function ($families) use ($handle): void {
                foreach ($families as $family) {
                    $hasPrimary = $family->familyMembers
                        ->contains(fn ($member) => (bool) $member->is_primary_contact)
                        ? 'yes'
                        : 'no';

                    fputcsv($handle, [
                        $family->family_name,
                        (int) $family->members_count,
                        $hasPrimary,
                        optional($family->created_at)->toDateString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
