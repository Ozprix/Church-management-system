<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreFinanceReportRequest;
use App\Http\Resources\Finance\FinanceReportResource;
use App\Models\FinanceReport;
use App\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FinanceReportController extends Controller
{
    public function __construct(private readonly FinanceReportService $service)
    {
        $this->middleware('feature:reports');
        $this->middleware('can:reports.finance_generate');
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $reports = FinanceReport::query()
            ->when($tenant, fn ($query) => $query->where('tenant_id', $tenant->id))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return FinanceReportResource::collection($reports)->response();
    }

    public function store(StoreFinanceReportRequest $request): JsonResponse
    {
        $report = $this->service->create($request->input('type'), $request->input('filters', []));

        return FinanceReportResource::make($report)->response()->setStatusCode(202);
    }

    public function show(FinanceReport $financeReport): FinanceReportResource
    {
        return FinanceReportResource::make($financeReport);
    }

    public function download(FinanceReport $financeReport)
    {
        abort_unless($financeReport->status === 'completed' && $financeReport->file_path, 404);

        $url = Storage::disk($financeReport->disk ?? 'reports')->temporaryUrl(
            $financeReport->file_path,
            now()->addMinutes(config('reports.download_ttl', 5))
        );

        return response()->json([
            'url' => $url,
        ]);
    }
}
