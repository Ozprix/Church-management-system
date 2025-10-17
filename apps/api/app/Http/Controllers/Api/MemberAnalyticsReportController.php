<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\StoreMemberAnalyticsReportRequest;
use App\Http\Requests\Analytics\UpdateMemberAnalyticsReportRequest;
use App\Http\Resources\MemberAnalyticsReportResource;
use App\Models\MemberAnalyticsReport;
use App\Services\Analytics\MemberAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberAnalyticsReportController extends Controller
{
    public function __construct(private readonly MemberAnalyticsService $analyticsService)
    {
        $this->middleware('feature:members');
        $this->middleware('can:members.view');
    }

    public function index(Request $request): JsonResponse
    {
        $reports = MemberAnalyticsReport::query()
            ->where('tenant_id', optional($request->attributes->get('tenant'))->id)
            ->orderBy('name')
            ->get();

        return MemberAnalyticsReportResource::collection($reports)->response();
    }

    public function store(StoreMemberAnalyticsReportRequest $request): JsonResponse
    {
        $report = MemberAnalyticsReport::create($request->validated());

        return MemberAnalyticsReportResource::make($report)->response()->setStatusCode(201);
    }

    public function show(MemberAnalyticsReport $memberAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($memberAnalyticsReport);

        return MemberAnalyticsReportResource::make($memberAnalyticsReport)->response();
    }

    public function update(UpdateMemberAnalyticsReportRequest $request, MemberAnalyticsReport $memberAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($memberAnalyticsReport);

        $memberAnalyticsReport->fill($request->validated());
        $memberAnalyticsReport->save();

        return MemberAnalyticsReportResource::make($memberAnalyticsReport)->response();
    }

    public function destroy(MemberAnalyticsReport $memberAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($memberAnalyticsReport);

        $memberAnalyticsReport->delete();

        return response()->json([], 204);
    }

    public function run(MemberAnalyticsReport $memberAnalyticsReport): JsonResponse
    {
        $this->authorizeReport($memberAnalyticsReport);

        $filters = $this->analyticsService->parseFilters($memberAnalyticsReport->filters ?? []);
        $metrics = $this->analyticsService->buildMetrics($filters);

        return response()->json($metrics);
    }

    public function export(MemberAnalyticsReport $memberAnalyticsReport): StreamedResponse
    {
        $this->authorizeReport($memberAnalyticsReport);

        $filters = $this->analyticsService->parseFilters($memberAnalyticsReport->filters ?? []);
        $query = $this->analyticsService
            ->applyFilters($memberAnalyticsReport->tenant->members()->with(['families', 'contacts'])->getQuery(), $filters)
            ->orderBy('last_name')
            ->orderBy('first_name');

        $filename = 'member-report-' . now()->format('Ymd_His') . '.csv';
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

    private function authorizeReport(MemberAnalyticsReport $report): void
    {
        $tenant = request()->attributes->get('tenant');
        abort_unless($tenant && $report->tenant_id === $tenant->id, 404);
    }
}
