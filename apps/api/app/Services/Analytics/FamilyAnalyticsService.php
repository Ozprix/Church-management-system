<?php

namespace App\Services\Analytics;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Support\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FamilyAnalyticsService
{
    public function __construct(private readonly TenantManager $tenantManager)
    {
    }

    /**
     * @return array{
     *     min_members?: int|null,
     *     max_members?: int|null,
     *     with_primary_contact?: bool|null,
     *     city?: string|null,
     *     state?: string|null,
     *     created_from?: ?Carbon,
     *     created_to?: ?Carbon
     * }
     */
    public function parseFilters(array $query): array
    {
        $minMembers = isset($query['min_members']) ? (int) $query['min_members'] : null;
        $maxMembers = isset($query['max_members']) ? (int) $query['max_members'] : null;

        $withPrimary = null;
        if (array_key_exists('with_primary_contact', $query)) {
            $withPrimary = filter_var($query['with_primary_contact'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        $createdFrom = null;
        if (! empty($query['created_from'])) {
            $createdFrom = Carbon::make($query['created_from'])?->startOfDay();
        }

        $createdTo = null;
        if (! empty($query['created_to'])) {
            $createdTo = Carbon::make($query['created_to'])?->endOfDay();
        }

        $city = isset($query['city']) && $query['city'] !== '' ? $query['city'] : null;
        $state = isset($query['state']) && $query['state'] !== '' ? $query['state'] : null;

        return [
            'min_members' => $minMembers,
            'max_members' => $maxMembers,
            'with_primary_contact' => $withPrimary,
            'city' => $city,
            'state' => $state,
            'created_from' => $createdFrom,
            'created_to' => $createdTo,
        ];
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        $query->withCount('members');

        if ($filters['created_from'] instanceof Carbon) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if ($filters['created_to'] instanceof Carbon) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (array_key_exists('with_primary_contact', $filters) && $filters['with_primary_contact'] !== null) {
            if ($filters['with_primary_contact']) {
                $query->whereHas('familyMembers', fn ($inner) => $inner->where('is_primary_contact', true));
            } else {
                $query->whereDoesntHave('familyMembers', fn ($inner) => $inner->where('is_primary_contact', true));
            }
        }

        if (! empty($filters['city'])) {
            $this->applyAddressFilter($query, 'city', $filters['city']);
        }

        if (! empty($filters['state'])) {
            $this->applyAddressFilter($query, 'state', $filters['state']);
        }

        if (! empty($filters['min_members'])) {
            $query->having('members_count', '>=', (int) $filters['min_members']);
        }

        if (! empty($filters['max_members'])) {
            $query->having('members_count', '<=', (int) $filters['max_members']);
        }

        return $query;
    }

    public function buildMetrics(array $filters): array
    {
        $baseQuery = $this->applyFilters(Family::query(), $filters);

        $totalFamilies = (clone $baseQuery)->count();

        $membersPerFamily = (clone $baseQuery)
            ->withCount('members')
            ->get();

        $averageSize = $membersPerFamily->avg('members_count') ?? 0.0;

        $withChildren = (clone $baseQuery)
            ->whereHas('familyMembers', fn ($inner) => $inner->where('relationship', 'child'))
            ->count();

        $withoutPrimaryContact = (clone $baseQuery)
            ->whereDoesntHave('familyMembers', fn ($inner) => $inner->where('is_primary_contact', true))
            ->count();

        $sizeDistribution = $this->sizeDistribution($membersPerFamily);
        $relationshipBreakdown = $this->relationshipBreakdown();

        $recentFamilies = $this->applyFilters(Family::query(), $filters)
            ->withCount('members')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Family $family) {
                return [
                    'id' => $family->id,
                    'family_name' => $family->family_name,
                    'members_count' => (int) $family->members_count,
                    'created_at' => optional($family->created_at)->toIso8601String(),
                ];
            });

        $familiesMissingPrimary = $this->applyFilters(Family::query(), $filters)
            ->whereDoesntHave('familyMembers', fn ($inner) => $inner->where('is_primary_contact', true))
            ->withCount('members')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Family $family) {
                return [
                    'id' => $family->id,
                    'family_name' => $family->family_name,
                    'members_count' => (int) $family->members_count,
                    'created_at' => optional($family->created_at)->toIso8601String(),
                ];
            });

        return [
            'totals' => [
                'families' => $totalFamilies,
                'average_household_size' => round($averageSize, 1),
                'families_with_children' => $withChildren,
                'families_without_primary_contact' => $withoutPrimaryContact,
            ],
            'size_distribution' => $sizeDistribution,
            'by_relationship' => $relationshipBreakdown,
            'recent_families' => $recentFamilies,
            'families_missing_primary' => $familiesMissingPrimary,
        ];
    }

    protected function sizeDistribution(Collection $membersPerFamily): array
    {
        $buckets = [
            '1' => 0,
            '2-3' => 0,
            '4-5' => 0,
            '6+' => 0,
        ];

        foreach ($membersPerFamily as $record) {
            $count = (int) ($record->members_count ?? 0);

            if ($count <= 1) {
                $buckets['1']++;
            } elseif ($count <= 3) {
                $buckets['2-3']++;
            } elseif ($count <= 5) {
                $buckets['4-5']++;
            } else {
                $buckets['6+']++;
            }
        }

        return collect($buckets)
            ->map(fn ($total, $label) => ['label' => $label, 'total' => (int) $total])
            ->values()
            ->all();
    }

    protected function relationshipBreakdown(): array
    {
        $tenantId = $this->tenantManager->getTenant()?->id;

        $query = FamilyMember::query()
            ->select('relationship')
            ->selectRaw('count(*) as total')
            ->groupBy('relationship')
            ->orderByDesc('total');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()
            ->map(fn ($row) => [
                'relationship' => $row->relationship ?? 'unspecified',
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    protected function applyAddressFilter(Builder $query, string $path, string $value): void
    {
        $valueLower = strtolower($value) . '%';
        $driver = $query->getConnection()->getDriverName();
        $jsonPath = '$.' . $path;

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $query->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(address, '" . $jsonPath . "'))) LIKE ?", [$valueLower]);
        } else {
            $query->whereRaw("LOWER(json_extract(address, '" . $jsonPath . "')) LIKE ?", [$valueLower]);
        }
    }
}
