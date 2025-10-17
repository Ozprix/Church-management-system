<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Http\JsonResponse;

class FamilyDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $this->authorizePermission('members.view');

        $familiesQuery = Family::query();
        $familyMembersQuery = FamilyMember::query();

        $totalFamilies = (clone $familiesQuery)->count();
        $totalIndividuals = (clone $familyMembersQuery)->distinct('member_id')->count('member_id');
        $familiesWithPrimary = (clone $familyMembersQuery)
            ->where('is_primary_contact', true)
            ->distinct('family_id')
            ->count('family_id');
        $familiesWithEmergency = (clone $familyMembersQuery)
            ->where('is_emergency_contact', true)
            ->distinct('family_id')
            ->count('family_id');

        $recentFamilies = Family::query()
            ->withCount('members')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Family $family) {
                return [
                    'id' => $family->id,
                    'family_name' => $family->family_name,
                    'members_count' => $family->members_count,
                    'created_at' => optional($family->created_at)->toIso8601String(),
                ];
            });

        $families = Family::query()->with(['members'])->get();

        $byCity = $families
            ->groupBy(function (Family $family) {
                $city = $family->address['city'] ?? 'Unknown';
                $city = is_string($city) && trim($city) !== '' ? trim($city) : 'Unknown';
                return mb_strtolower($city);
            })
            ->map(function ($group, $cityKey) use ($families) {
                $cityName = $group->first()->address['city'] ?? 'Unknown';
                if (!is_string($cityName) || trim($cityName) === '') {
                    $cityName = 'Unknown';
                }

                return [
                    'city' => $cityName,
                    'total' => $group->count(),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->take(6)
            ->values();

        $trendStart = now()->startOfWeek()->subWeeks(7);
        $trendBuckets = [];
        for ($i = 0; $i < 8; $i++) {
            $weekStart = (clone $trendStart)->addWeeks($i);
            $trendBuckets[$weekStart->format('Y-m-d')] = [
                'label' => $weekStart->format('M d'),
                'total' => 0,
            ];
        }

        Family::query()
            ->where('created_at', '>=', $trendStart)
            ->select(['created_at'])
            ->orderBy('created_at')
            ->chunk(200, function ($chunk) use (&$trendBuckets, $trendStart) {
                foreach ($chunk as $family) {
                    if (!$family->created_at) {
                        continue;
                    }
                    $bucket = $family->created_at->copy()->startOfWeek();
                    if ($bucket->lt($trendStart)) {
                        $bucket = $trendStart->copy();
                    }
                    $key = $bucket->format('Y-m-d');
                    if (!isset($trendBuckets[$key])) {
                        $trendBuckets[$key] = [
                            'label' => $bucket->format('M d'),
                            'total' => 0,
                        ];
                    }
                    $trendBuckets[$key]['total']++;
                }
            });

        $familiesMissingPrimary = $families
            ->filter(function (Family $family) {
                return !$family->members->contains(function ($member) {
                    return (bool) ($member->pivot->is_primary_contact ?? false);
                });
            });

        $familiesMissingEmergency = $families
            ->filter(function (Family $family) {
                return !$family->members->contains(function ($member) {
                    return (bool) ($member->pivot->is_emergency_contact ?? false);
                });
            });

        $reminderList = $familiesMissingPrimary
            ->merge($familiesMissingEmergency)
            ->unique('id')
            ->take(5)
            ->map(function (Family $family) {
                return [
                    'id' => $family->id,
                    'family_name' => $family->family_name,
                    'members_count' => $family->members->count(),
                ];
            });

        $upcomingAnniversaries = $families
            ->filter(fn (Family $family) => $family->created_at !== null)
            ->map(function (Family $family) {
                $createdAt = $family->created_at->copy();
                $today = now()->startOfDay();
                $nextAnniversary = $createdAt->copy()->year($today->year);
                if ($nextAnniversary->lt($today)) {
                    $nextAnniversary->addYear();
                }

                return [
                    'id' => $family->id,
                    'family_name' => $family->family_name,
                    'anniversary_on' => $nextAnniversary->toIso8601String(),
                    'days_until' => $today->diffInDays($nextAnniversary),
                ];
            })
            ->filter(fn ($data) => $data['days_until'] <= 45)
            ->sortBy('days_until')
            ->take(5)
            ->values();

        return response()->json([
            'stats' => [
                'total_families' => $totalFamilies,
                'total_individuals' => $totalIndividuals,
                'families_with_primary_contact' => $familiesWithPrimary,
                'families_without_primary_contact' => max($totalFamilies - $familiesWithPrimary, 0),
                'families_with_emergency_contact' => $familiesWithEmergency,
            ],
            'by_city' => $byCity,
            'new_families_trend' => array_values($trendBuckets),
            'recent_families' => $recentFamilies,
            'reminders' => [
                'missing_primary_contact_total' => $familiesMissingPrimary->count(),
                'missing_emergency_contact_total' => $familiesMissingEmergency->count(),
                'suggested_families' => $reminderList,
            ],
            'upcoming_anniversaries' => $upcomingAnniversaries,
        ]);
    }
}
