<?php

namespace Database\Seeders;

use App\Models\Gathering;
use App\Models\Member;
use App\Models\Service;
use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\VolunteerAssignment;
use App\Services\Rbac\RbacManager;
use App\Services\VolunteerPipelineService;
use App\Services\VolunteerService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class VolunteerSeeder extends Seeder
{
    public function run(): void
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create([
            'name' => 'Volunteer Ministries Demo',
            'slug' => 'volunteer-ministries-demo',
            'timezone' => 'America/New_York',
            'plan' => 'standard',
            'status' => 'active',
        ]);

        /** @var TenantManager $tenantManager */
        $tenantManager = app(TenantManager::class);
        $tenantManager->setTenant($tenant);

        /** @var RbacManager $rbacManager */
        $rbacManager = app(RbacManager::class);
        $rbacManager->bootstrapTenant($tenant);

        /** @var VolunteerService $volunteerService */
        $volunteerService = app(VolunteerService::class);

        /** @var VolunteerPipelineService $pipelineService */
        $pipelineService = app(VolunteerPipelineService::class);

        /** @var Collection<int, Member> $members */
        $members = Member::factory()
            ->count(12)
            ->create(['tenant_id' => $tenant->id])
            ->values();

        $serviceDefinitions = [
            [
                'name' => 'Sunday Worship Gathering',
                'short_code' => 'SUN',
                'default_location' => 'Main Auditorium',
                'default_start_time' => '09:00:00',
                'default_duration_minutes' => 120,
                'weekday' => Carbon::SUNDAY,
            ],
            [
                'name' => 'Youth Night',
                'short_code' => 'YTH',
                'default_location' => 'Student Chapel',
                'default_start_time' => '18:30:00',
                'default_duration_minutes' => 150,
                'weekday' => Carbon::FRIDAY,
            ],
            [
                'name' => 'Community Outreach',
                'short_code' => 'OUT',
                'default_location' => 'Downtown Center',
                'default_start_time' => '10:00:00',
                'default_duration_minutes' => 180,
                'weekday' => Carbon::SATURDAY,
            ],
        ];

        $services = collect();
        $gatherings = collect();
        $now = Carbon::now($tenant->timezone ?? 'UTC');

        foreach ($serviceDefinitions as $definition) {
            /** @var Service $service */
            $service = Service::factory()->create([
                'tenant_id' => $tenant->id,
                'name' => $definition['name'],
                'short_code' => $definition['short_code'],
                'description' => $definition['name'] . ' service seeded for demo purposes.',
                'default_location' => $definition['default_location'],
                'default_start_time' => $definition['default_start_time'],
                'default_duration_minutes' => $definition['default_duration_minutes'],
                'absence_threshold' => 3,
            ]);

            $services->push($service);

            foreach ([1, 2] as $weekOffset) {
                $startsAt = (clone $now)
                    ->next($definition['weekday'])
                    ->addWeeks($weekOffset - 1)
                    ->setTimeFromTimeString($definition['default_start_time']);

                $gatherings->push(
                    Gathering::factory()
                        ->forService($service)
                        ->create([
                            'tenant_id' => $tenant->id,
                            'name' => $service->name . ' ' . $startsAt->format('M j'),
                            'starts_at' => $startsAt,
                            'ends_at' => (clone $startsAt)->addMinutes($definition['default_duration_minutes']),
                            'status' => 'scheduled',
                            'location' => $definition['default_location'],
                            'notes' => 'Seeded gathering for volunteer scheduling demos.',
                        ])
                );
            }
        }

        $roleDefinitions = [
            [
                'name' => 'Sunday Greeter',
                'description' => 'Welcomes attendees, answers questions, and creates a warm first impression.',
                'skills_required' => ['hospitality', 'communication'],
            ],
            [
                'name' => 'Worship Vocalist',
                'description' => 'Leads congregational singing as part of the worship band.',
                'skills_required' => ['music', 'teamwork'],
            ],
            [
                'name' => 'Audio Technician',
                'description' => 'Runs the sound board and monitors audio levels during services.',
                'skills_required' => ['tech', 'problem-solving'],
            ],
            [
                'name' => 'Kids Ministry Teacher',
                'description' => 'Teaches curriculum and facilitates activities for children.',
                'skills_required' => ['teaching', 'hospitality'],
            ],
        ];

        $roles = collect($roleDefinitions)->mapWithKeys(function (array $definition) use ($tenant, $volunteerService) {
            $slug = Str::slug($definition['name']);

            $role = $volunteerService->createRole([
                'tenant_id' => $tenant->id,
                'name' => $definition['name'],
                'slug' => $slug,
                'description' => $definition['description'],
                'skills_required' => $definition['skills_required'],
            ]);

            return [$slug => $role];
        });

        $teamDefinitions = [
            [
                'name' => 'Guest Experience Team',
                'description' => 'Creates a welcoming environment from the parking lot to the sanctuary.',
                'metadata' => ['contact_email' => 'welcome@demo-church.test'],
                'roles' => ['sunday-greeter', 'kids-ministry-teacher'],
            ],
            [
                'name' => 'Worship Arts Team',
                'description' => 'Oversees music, production, and creative elements for services.',
                'metadata' => ['rehearsal_day' => 'Thursday'],
                'roles' => ['worship-vocalist', 'audio-technician'],
            ],
            [
                'name' => 'Family Ministries Team',
                'description' => 'Supports families with discipleship resources and programming.',
                'metadata' => ['room_assignment' => 'Education Wing 2B'],
                'roles' => ['kids-ministry-teacher'],
            ],
        ];

        $teams = collect($teamDefinitions)->mapWithKeys(function (array $definition) use ($tenant, $volunteerService, $roles) {
            $slug = Str::slug($definition['name']);

            $team = $volunteerService->createTeam([
                'tenant_id' => $tenant->id,
                'name' => $definition['name'],
                'slug' => $slug,
                'description' => $definition['description'],
                'metadata' => $definition['metadata'],
                'role_ids' => collect($definition['roles'])
                    ->filter(fn (string $roleKey) => $roles->has($roleKey))
                    ->map(fn (string $roleKey) => $roles->get($roleKey)->id)
                    ->all(),
            ]);

            return [$slug => $team];
        });

        foreach ($members->take(4) as $index => $member) {
            $volunteerService->updateAvailability([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'weekdays' => match ($index) {
                    0 => ['sunday'],
                    1 => ['saturday', 'sunday'],
                    2 => ['friday', 'sunday'],
                    default => ['wednesday', 'sunday'],
                },
                'time_blocks' => [
                    ['start' => '07:30', 'end' => '11:30'],
                    ['start' => '16:30', 'end' => '20:30'],
                ],
                'notes' => $index === 1
                    ? 'Prefers second service when possible.'
                    : 'Seeded availability from volunteer seeder.',
            ]);
        }

        $assignmentsToSeed = [
            [
                'member' => $members->get(0),
                'role' => $roles->get('sunday-greeter'),
                'team' => $teams->get('guest-experience-team'),
                'gathering' => $gatherings->get(0),
                'status' => 'confirmed',
            ],
            [
                'member' => $members->get(1),
                'role' => $roles->get('kids-ministry-teacher'),
                'team' => $teams->get('family-ministries-team'),
                'gathering' => $gatherings->get(1),
                'status' => 'scheduled',
            ],
            [
                'member' => $members->get(2),
                'role' => $roles->get('worship-vocalist'),
                'team' => $teams->get('worship-arts-team'),
                'gathering' => $gatherings->get(2),
                'status' => 'confirmed',
            ],
            [
                'member' => $members->get(3),
                'role' => $roles->get('audio-technician'),
                'team' => $teams->get('worship-arts-team'),
                'gathering' => $gatherings->get(3),
                'status' => 'scheduled',
            ],
            [
                'member' => $members->get(4),
                'role' => $roles->get('sunday-greeter'),
                'team' => $teams->get('guest-experience-team'),
                'gathering' => $gatherings->get(4),
                'status' => 'scheduled',
            ],
        ];

        foreach ($assignmentsToSeed as $assignmentData) {
            if (! $assignmentData['member'] || ! $assignmentData['role'] || ! $assignmentData['gathering']) {
                continue;
            }

            $startsAt = $assignmentData['gathering']->starts_at->copy()->subMinutes(45);
            $volunteerService->assign([
                'tenant_id' => $tenant->id,
                'member_id' => $assignmentData['member']->id,
                'volunteer_role_id' => $assignmentData['role']->id,
                'volunteer_team_id' => $assignmentData['team']?->id,
                'gathering_id' => $assignmentData['gathering']->id,
                'starts_at' => $startsAt,
                'ends_at' => $assignmentData['gathering']->ends_at?->copy()->addMinutes(15) ?? (clone $startsAt)->addHours(2),
                'status' => $assignmentData['status'],
                'notes' => [
                    'assigned_by' => 'VolunteerSeeder',
                ],
            ]);
        }

        $websiteSignup = $pipelineService->submitSignup([
            'tenant_id' => $tenant->id,
            'volunteer_role_id' => $roles->get('sunday-greeter')->id,
            'volunteer_team_id' => $teams->get('guest-experience-team')->id,
            'name' => 'Taylor Brooks',
            'email' => 'taylor.brooks@example.test',
            'phone' => '555-0108',
            'notes' => 'Submitted via public website form.',
            'metadata' => ['source' => 'website'],
        ]);

        $memberSignup = $pipelineService->submitSignup([
            'tenant_id' => $tenant->id,
            'volunteer_role_id' => $roles->get('audio-technician')->id,
            'volunteer_team_id' => $teams->get('worship-arts-team')->id,
            'member_id' => $members->get(5)?->id,
            'notes' => 'Expressed interest after service.',
            'metadata' => ['source' => 'connection-card'],
        ]);

        if ($memberSignup) {
            $pipelineService->updateSignup($memberSignup, ['status' => 'reviewed']);

            $targetGathering = $gatherings->get(5) ?? $gatherings->last();

            $pipelineService->updateSignup($memberSignup, [
                'status' => 'confirmed',
                'assignment' => [
                    'tenant_id' => $tenant->id,
                    'volunteer_role_id' => $roles->get('audio-technician')->id,
                    'volunteer_team_id' => $teams->get('worship-arts-team')->id,
                    'member_id' => $memberSignup->member_id,
                    'gathering_id' => $targetGathering?->id,
                    'starts_at' => $targetGathering?->starts_at?->copy()->subMinutes(60) ?? (clone $now)->addWeeks(3)->setTime(7, 30),
                    'ends_at' => $targetGathering?->ends_at ?? (clone $now)->addWeeks(3)->setTime(11, 0),
                    'status' => 'confirmed',
                ],
            ]);
        }

        $assignments = VolunteerAssignment::query()
            ->where('tenant_id', $tenant->id)
            ->with('member')
            ->get();

        if ($assignments->isNotEmpty()) {
            $firstAssignment = $assignments->first();

            $pipelineService->recordHours([
                'tenant_id' => $tenant->id,
                'volunteer_assignment_id' => $firstAssignment->id,
                'member_id' => $firstAssignment->member_id,
                'served_on' => (clone $now)->subWeek()->toDateString(),
                'hours' => 3.5,
                'source' => 'manual',
                'notes' => 'Seeded attendance from check-in sheet.',
                'metadata' => ['recorded_by' => 'volunteer_admin'],
            ]);
        }

        $pipelineService->recordHours([
            'tenant_id' => $tenant->id,
            'member_id' => $members->get(6)?->id,
            'served_on' => (clone $now)->subDays(2)->toDateString(),
            'hours' => 2.0,
            'source' => 'self_report',
            'notes' => 'Hours submitted through the member portal.',
            'metadata' => ['approval_status' => 'pending'],
        ]);

        $roles->each(fn ($role) => $volunteerService->refreshRoleAnalytics($role->fresh()));

        $tenantManager->forgetTenant();
    }
}
