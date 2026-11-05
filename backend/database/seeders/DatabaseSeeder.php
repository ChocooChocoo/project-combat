<?php

namespace Database\Seeders;

use App\Enums\GymPersonType;
use App\Enums\GymStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\GymService;
use App\Models\MembershipPlan;
use App\Models\TrainingSession;
use App\Models\User;
use App\Notifications\GymApplicationDecided;
use App\Notifications\LeaveRequestDecided;
use App\Notifications\LeaveRequestSubmitted;
use App\Support\CurrentGym;
use App\Support\SessionScheduler;
use App\Support\WorkingDays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Realistic demo data for the Combat Platform.
 *
 * Running this seeder replaces every application record in the database.
 * All seeded accounts use the password "Password_123".
 */
class DatabaseSeeder extends Seeder
{
    private const PASSWORD = 'Password_123';

    /** Roles every approved gym has, with the access levels the owner gave them. */
    private const ROLES = [
        'Operations Manager' => [
            'hr' => 'manage',
            'gym_setup' => 'manage',
            'training' => 'manage',
            'members' => 'manage',
            'health_records' => 'view',
            'payroll' => 'view',
            'finance' => 'view',
            'customers' => 'view',
            'inventory' => 'view',
            'procurement' => 'view',
            'announcements' => 'manage',
            'decision_support' => 'view',
        ],
        'Head Coach' => [
            'training' => 'approve',
            'members' => 'view',
            'health_records' => 'view',
            'announcements' => 'view',
        ],
        'Coach' => [
            'training' => 'manage',
            'members' => 'view',
        ],
        'Front Desk' => [
            'members' => 'approve',
            'training' => 'manage',
            'finance' => 'manage',
            'customers' => 'manage',
            'announcements' => 'manage',
        ],
        'HR Officer' => [
            'hr' => 'manage',
            'payroll' => 'manage',
            'announcements' => 'view',
        ],
        'Finance Officer' => [
            'finance' => 'approve',
            'payroll' => 'approve',
            'procurement' => 'approve',
            'decision_support' => 'view',
        ],
        'Sports Therapist' => [
            'health_records' => 'manage',
            'training' => 'view',
            'members' => 'view',
        ],
    ];

    public function run(): void
    {
        $this->resetData();

        $year = (int) now()->year;

        /** @var array<string, User> $people */
        $people = $this->createPeople();

        $admin = $people['admin'];

        $this->seedMembers($people);
        $this->seedApprovedGyms($people, $admin, $year);
        $this->seedApplications($people, $admin);
    }

    /** Removes every record this platform created, and the files that belong to it. */
    private function resetData(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'notifications',
            'one_time_codes',
            'personal_access_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'session_notes',
            'gym_visits',
            'session_bookings',
            'training_sessions',
            'athlete_entries',
            'athlete_profiles',
            'membership_events',
            'payments',
            'memberships',
            'gym_service_photos',
            'membership_plan_addons',
            'gym_services',
            'membership_plans',
            'gym_preferences',
            'gym_holidays',
            'leave_balances',
            'leave_requests',
            'work_schedules',
            'employee_profiles',
            'rooms',
            'gym_documents',
            'gym_role_user',
            'gym_roles',
            'gym_user',
            'gyms',
            'users',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        Storage::disk('public')->deleteDirectory('gym-services');
        Storage::disk('local')->deleteDirectory('gym-applications');
        Storage::disk('local')->deleteDirectory('leave-attachments');
    }

    /** @return array<string, User> */
    private function createPeople(): array
    {
        $people = [];

        $people['admin'] = $this->user('Marisol', 'Santiago', 'admin@combatplatform.ph', '0917 555 0100', true);

        $people['ramon'] = $this->user('Ramon', 'Villanueva', 'ramon.villanueva@ironwillboxing.ph', '0917 823 4156');
        $people['andrea'] = $this->user('Andrea', 'Lim', 'andrea.lim@kalawcombat.ph', '0917 987 6543');
        $people['carlo'] = $this->user('Carlo', 'Mendoza', 'carlo.mendoza@bakunawama.ph', '0932 987 6541');
        $people['maria'] = $this->user('Maria', 'Bautista', 'maria.bautista@cordillerafightcamp.ph', '0917 888 9900');
        $people['paolo'] = $this->user('Paolo', 'Reyes', 'paolo.reyes@davaograppling.ph', '0920 888 7766');

        $people['jomar_aquino'] = $this->user('Jomar', 'Aquino', 'jomar.aquino@southsidemuaythai.ph', '0910 345 6789');
        $people['grace'] = $this->user('Grace', 'Fuentes', 'grace.fuentes@visayasfightlab.ph', '0917 123 9876');
        $people['nestor'] = $this->user('Nestor', 'Cabral', 'nestor.cabral@tagumboxing.ph', '0927 345 6781');
        $people['rebecca'] = $this->user('Rebecca', 'Marasigan', 'rebecca.marasigan@mindorocombat.ph', '0915 467 8901');
        $people['luis'] = $this->user('Luis', 'Herrera', 'luis.herrera@gmail.com', '0916 555 1234');

        $people['sofia'] = $this->user('Sofia', 'Ramirez', 'sofia.ramirez@gmail.com', '0917 555 0201');
        $people['gabriel_tan'] = $this->user('Gabriel', 'Tan', 'gabriel.tan@gmail.com', '0917 555 0202');
        $people['patricia_g'] = $this->user('Patricia', 'Gomez', 'patricia.gomez@gmail.com', '0917 555 0203');
        $people['daniel'] = $this->user('Daniel', 'Villamor', 'daniel.villamor@gmail.com', '0917 555 0204');
        $people['camille'] = $this->user('Camille', 'Navarro', 'camille.navarro@gmail.com', '0917 555 0205');
        $people['miguel_s'] = $this->user('Miguel', 'Soriano', 'miguel.soriano@gmail.com', '0917 555 0206');
        $people['jasmine'] = $this->user('Jasmine', 'Co', 'jasmine.co@gmail.com', '0917 555 0207');
        $people['anthony'] = $this->user('Anthony', 'Padilla', 'anthony.padilla@gmail.com', '0917 555 0208');
        $people['nicole'] = $this->user('Nicole', 'Enriquez', 'nicole.enriquez@gmail.com', '0917 555 0209');
        $people['christian'] = $this->user('Christian', 'Roxas', 'christian.roxas@gmail.com', '0917 555 0210');
        $people['alyssa'] = $this->user('Alyssa', 'Mercado', 'alyssa.mercado@gmail.com', '0917 555 0211');

        return $people;
    }

    private function user(
        string $firstName,
        string $lastName,
        string $email,
        string $phone,
        bool $platformAdmin = false,
    ): User {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => self::PASSWORD,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'is_platform_admin' => $platformAdmin,
            'password_change_required' => false,
        ])->save();

        return $user;
    }

    /** @param array<string, User> $people */
    private function seedMembers(array $people): void
    {
        $preferences = [
            'sofia' => ['boxing', 1000, 3000, ['evening']],
            'gabriel_tan' => ['bjj', 1500, 3500, ['evening']],
            'patricia_g' => ['muay_thai', 800, 2500, ['morning']],
            'daniel' => ['mma', 1200, 3200, ['evening']],
            'camille' => ['taekwondo', 800, 2000, ['afternoon']],
            'miguel_s' => ['boxing', 1000, 2600, ['morning']],
            'jasmine' => ['bjj', 1200, 3000, ['morning']],
            'anthony' => ['wrestling', 900, 2400, ['evening']],
            'nicole' => ['arnis', 700, 1800, ['afternoon']],
            'christian' => ['boxing', 800, 2500, ['evening']],
            'alyssa' => ['kickboxing', 1000, 2800, ['morning']],
        ];

        foreach ($preferences as $key => [$discipline, $minPrice, $maxPrice, $times]) {
            $people[$key]->gymPreference()->create([
                'discipline' => $discipline,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'preferred_times' => $times,
            ]);
        }
    }

    /** @param array<string, User> $people */
    private function seedApprovedGyms(array $people, User $admin, int $year): void
    {
        foreach ($this->approvedGymSpecs() as $spec) {
            $owner = $people[$spec['owner']];
            $gym = $this->createGym($spec, $owner, $admin);

            $this->seedGymDocuments($gym, $owner, $spec);

            app(CurrentGym::class)->set($gym);

            $gym->addPerson($owner, GymPersonType::Owner);

            $roles = [];
            foreach (self::ROLES as $name => $permissions) {
                $roles[$name] = GymRole::create([
                    'name' => $name,
                    'permissions' => $permissions,
                ]);
            }

            $employees = [];

            foreach ($spec['staff'] as $staff) {
                $employee = $this->user(
                    $staff['first'],
                    $staff['last'],
                    $staff['email'],
                    $staff['phone'],
                );

                $gym->addPerson($employee, GymPersonType::Employee);

                foreach ((array) ($staff['roles'] ?? [$staff['role']]) as $roleName) {
                    $roles[$roleName]->users()->attach($employee->id);
                }

                $gym->employees()->create([
                    'user_id' => $employee->id,
                    'job_title' => $staff['job_title'],
                    'started_on' => $staff['started_on'],
                    'pay_basis' => $staff['pay_basis'],
                    'monthly_rate' => $staff['monthly_rate'] ?? null,
                    'per_session_rate' => $staff['per_session_rate'] ?? null,
                    'coach_experience' => $staff['coach_experience'] ?? null,
                    'coach_rank' => $staff['coach_rank'] ?? null,
                    'coach_specialisation' => $staff['coach_specialisation'] ?? null,
                    'coach_bio' => $staff['coach_bio'] ?? null,
                    'coach_public' => $staff['coach_public'] ?? false,
                ]);

                $this->seedSchedule($gym, $employee, $staff['schedule']);
                $this->seedLeaveBalances($gym, $employee, $year, $staff['leave_overrides'] ?? []);

                $employees[$staff['email']] = $employee;
            }

            foreach ($spec['rooms'] as $room) {
                $gym->rooms()->create(['name' => $room, 'is_active' => true]);
            }

            $services = [];

            foreach ($spec['gym_services'] as $index => $data) {
                $service = new GymService($data);
                $service->gym_id = $gym->id;
                $service->published_at = now()->subDays(45 - $index);
                $service->save();

                $this->attachServiceArtwork($gym, $service, $spec['palette']);
                $services[$service->name] = $service;
            }

            foreach ($spec['plans'] as $index => $data) {
                $addons = $data['addons'] ?? [];
                unset($data['addons']);

                $plan = new MembershipPlan($data);
                $plan->gym_id = $gym->id;
                $plan->published_at = now()->subDays(40 - $index);
                $plan->save();

                foreach ($addons as $addon) {
                    $plan->addons()->create([
                        'gym_service_id' => $services[$addon['service']]->id,
                        'price' => $addon['price'],
                    ]);
                }
            }

            foreach ($spec['holidays'] as $holiday) {
                $gym->holidays()->create($holiday);
            }

            foreach ($spec['leaves'] as $leave) {
                $this->seedLeave($gym, $owner, $employees[$leave['email']], $leave);
            }

            foreach ($spec['members'] as $member) {
                $gym->addPerson($people[$member], GymPersonType::Member);
            }

            $this->seedSessions($gym, $owner, $spec, $employees, $services);

            $owner->notify(new GymApplicationDecided($gym));
        }
    }

    /** R-42: sample classes use the same holiday, leave and clash checks as staff scheduling. */
    private function seedSessions(Gym $gym, User $owner, array $spec, array $employees, array $services): void
    {
        $classes = array_values(array_filter($services, fn ($service) => $service->activity_type === 'classes'));
        $room = $gym->rooms()->first();
        if (! $classes || ! $room) {
            return;
        }
        $scheduler = app(SessionScheduler::class);
        foreach (range(1, 13, 2) as $index => $offset) {
            $date = today()->addDays($offset);
            $weekday = strtolower($date->format('D'));
            $service = $classes[$index % count($classes)];
            foreach ($spec['staff'] as $staff) {
                if (empty($staff['coach_public']) || empty($staff['schedule'][$weekday])) {
                    continue;
                }
                $coach = $employees[$staff['email']];
                $start = Carbon::parse($date->toDateString().' '.$staff['schedule'][$weekday][0])->addHour();
                $end = $start->copy()->addMinutes($service->duration_minutes ?? 60);
                if ($scheduler->problems($coach->id, $room->id, $start, $end) !== []) {
                    continue;
                }
                TrainingSession::create([
                    'gym_service_id' => $service->id, 'type' => 'classes', 'name' => $service->name,
                    'discipline' => $service->discipline, 'level' => $service->level ?? 'all',
                    'coach_id' => $coach->id, 'room_id' => $room->id,
                    'starts_at' => $start, 'ends_at' => $end, 'capacity' => 12,
                    'content' => $service->overview, 'bring' => $service->bring,
                    'fee' => $service->price_included ? null : $service->price,
                    'status' => 'scheduled', 'created_by' => $owner->id,
                ]);
                break;
            }
        }
    }

    /**
     * @param  array<string, User>  $people
     */
    private function seedApplications(array $people, User $admin): void
    {
        foreach ($this->applicationSpecs() as $spec) {
            $owner = $people[$spec['owner']];
            $gym = $this->createGym($spec, $owner, $admin);

            if ($gym->status !== GymStatus::Draft) {
                $this->seedGymDocuments($gym, $owner, $spec);
            }

            if (in_array($gym->status, [GymStatus::Approved, GymStatus::Returned, GymStatus::Rejected], true)) {
                $owner->notify(new GymApplicationDecided($gym));
            }
        }
    }

    /** @param array<string, mixed> $spec */
    private function createGym(array $spec, User $owner, User $admin): Gym
    {
        $gym = new Gym;
        $gym->fill([
            'name' => $spec['name'],
            'tier' => $spec['tier'],
            'address_line' => $spec['address_line'],
            'barangay' => $spec['barangay'],
            'city' => $spec['city'],
            'province' => $spec['province'],
            'latitude' => $spec['latitude'],
            'longitude' => $spec['longitude'],
            'contact_phone' => $spec['contact_phone'],
            'contact_email' => $spec['contact_email'],
            'operating_hours' => $spec['operating_hours'],
            'disciplines' => $spec['disciplines'],
            'services' => $spec['services'],
            'facilities' => $spec['facilities'],
            'activity_types' => $spec['activity_types'] ?? null,
            'working_days' => $spec['working_days'] ?? null,
            'policies' => $spec['policies'] ?? null,
        ]);

        $status = GymStatus::from($spec['status']);

        $gym->applicant_id = $owner->id;
        $gym->status = $status;
        $gym->submitted_at = isset($spec['submitted_days_ago'])
            ? now()->subDays($spec['submitted_days_ago'])
            : null;

        if (in_array($status, [GymStatus::Approved, GymStatus::Returned, GymStatus::Rejected], true)) {
            $gym->reviewed_by = $admin->id;
            $gym->reviewed_at = now()->subDays($spec['reviewed_days_ago'] ?? 1);
        }

        if ($status === GymStatus::Approved) {
            $gym->approved_tier = $spec['tier'];
            $gym->checklist = $this->checklist($spec['tier'], true);
        }

        if ($status === GymStatus::UnderReview) {
            $gym->checklist = $spec['checklist'] ?? $this->checklist($spec['tier'], false);
        }

        if (isset($spec['return_items'])) {
            $gym->return_items = $spec['return_items'];
        }

        if (isset($spec['decision_reason'])) {
            $gym->decision_reason = $spec['decision_reason'];
        }

        if (isset($spec['checklist']) && $status !== GymStatus::UnderReview) {
            $gym->checklist = $spec['checklist'];
        }

        $gym->save();

        return $gym;
    }

    /** @return array<string, bool> */
    private function checklist(int $tier, bool $complete): array
    {
        $items = [];

        foreach (config('verification.checklist') as $key => $item) {
            if (in_array($tier, $item['tiers'], true)) {
                $items[$key] = $complete;
            }
        }

        return $items;
    }

    /** @param array<string, array{0: string, 1: string}> $week */
    private function seedSchedule(Gym $gym, User $employee, array $week): void
    {
        foreach ($week as $weekday => [$start, $end]) {
            $gym->workSchedules()->create([
                'user_id' => $employee->id,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
            ]);
        }
    }

    /** @param array<string, int> $overrides */
    private function seedLeaveBalances(Gym $gym, User $employee, int $year, array $overrides = []): void
    {
        $types = [
            'vacation' => $overrides['vacation'] ?? 15,
            'sick' => $overrides['sick'] ?? 15,
            'service_incentive' => $overrides['service_incentive'] ?? 5,
        ];

        foreach ($types as $type => $allowance) {
            $gym->leaveBalances()->create([
                'user_id' => $employee->id,
                'type' => $type,
                'year' => $year,
                'allowance' => $allowance,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function seedLeave(Gym $gym, User $owner, User $employee, array $data): void
    {
        $from = Carbon::parse($data['starts_on'])->startOfDay();
        $to = Carbon::parse($data['ends_on'])->startOfDay();
        $status = LeaveRequestStatus::from($data['status']);

        $leave = $gym->leaveRequests()->create([
            'user_id' => $employee->id,
            'type' => $data['type'],
            'starts_on' => $from->toDateString(),
            'ends_on' => $to->toDateString(),
            'working_days' => app(WorkingDays::class)->count($gym, $employee, $from, $to),
            'reason' => $data['reason'],
            'status' => $status->value,
        ]);

        if (in_array($status, [LeaveRequestStatus::Approved, LeaveRequestStatus::Rejected], true)) {
            $leave->forceFill([
                'decided_by' => $owner->id,
                'decided_at' => now()->subDays($data['decided_days_ago'] ?? 3),
                'decision_note' => $data['decision_note'] ?? null,
            ])->save();

            $employee->notify(new LeaveRequestDecided($gym, $leave));
        }

        if ($status === LeaveRequestStatus::Pending) {
            $owner->notify(new LeaveRequestSubmitted(
                $gym,
                $leave,
                trim("{$employee->first_name} {$employee->last_name}"),
            ));
        }
    }

    private function attachServiceArtwork(Gym $gym, GymService $service, array $palette): void
    {
        $base = "gym-services/{$gym->id}";

        $cover = "{$base}/{$service->id}-cover.svg";
        Storage::disk('public')->put($cover, $this->serviceArtwork($gym, $service, $palette, 0));

        $service->forceFill(['cover_photo_path' => $cover])->save();

        $gallery = "{$base}/{$service->id}-gallery.svg";
        Storage::disk('public')->put($gallery, $this->serviceArtwork($gym, $service, $palette, 1));

        $service->photos()->create(['path' => $gallery, 'sort' => 1]);
    }

    private function serviceArtwork(Gym $gym, GymService $service, array $palette, int $variant): string
    {
        $gymName = $this->xml($gym->name);
        $serviceName = $this->xml($service->name);
        $accent = $palette[2] ?? '#f59e0b';
        $band = $variant === 0 ? 180 : 320;
        $circleX = $variant === 0 ? 1010 : 280;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$palette[0]}"/>
      <stop offset="1" stop-color="{$palette[1]}"/>
    </linearGradient>
  </defs>
  <rect width="1280" height="720" fill="url(#bg)"/>
  <circle cx="{$circleX}" cy="150" r="260" fill="{$accent}" opacity="0.14"/>
  <circle cx="{$circleX}" cy="560" r="180" fill="#ffffff" opacity="0.06"/>
  <path d="M0 {$band} C240 120 420 260 640 200 C860 140 1040 260 1280 170 L1280 250 C1040 340 860 220 640 280 C420 340 240 200 0 260 Z" fill="#ffffff" opacity="0.08"/>
  <path d="M0 470 C240 400 420 540 640 480 C860 420 1040 540 1280 450" fill="none" stroke="{$accent}" stroke-width="5" opacity="0.65"/>
  <text x="72" y="96" fill="{$accent}" font-family="Arial, Helvetica, sans-serif" font-size="24" letter-spacing="4">COMBAT PLATFORM</text>
  <text x="72" y="300" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="58" font-weight="700">{$serviceName}</text>
  <text x="72" y="364" fill="#ffffff" opacity="0.78" font-family="Arial, Helvetica, sans-serif" font-size="28">{$gymName}</text>
  <text x="72" y="624" fill="#ffffff" opacity="0.62" font-family="Arial, Helvetica, sans-serif" font-size="22">Demo training image</text>
</svg>
SVG;
    }

    private function seedGymDocuments(Gym $gym, User $owner, array $spec): void
    {
        $types = $spec['tier'] === 2
            ? ['dti', 'mayors_permit', 'barangay_clearance', 'facility_photo', 'video_tour', 'owner_id', 'owner_selfie', 'utility_bill']
            : ['dti', 'mayors_permit', 'barangay_clearance'];

        foreach ($types as $type) {
            $count = $type === 'facility_photo' ? 10 : 1;

            for ($index = 1; $index <= $count; $index++) {
                [$path, $originalName, $mime, $contents] = $this->documentFile(
                    $gym,
                    $owner,
                    $type,
                    $index,
                    $spec['palette'],
                );

                Storage::disk('local')->put($path, $contents);

                $gym->documents()->create([
                    'type' => $type,
                    'path' => $path,
                    'original_name' => $originalName,
                    'mime' => $mime,
                    'size' => strlen($contents),
                ]);
            }
        }
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function documentFile(Gym $gym, User $owner, string $type, int $index, array $palette): array
    {
        $base = "gym-applications/{$gym->id}";
        $slug = Str::slug($gym->name);
        $ownerName = "{$owner->first_name} {$owner->last_name}";
        $address = "{$gym->address_line}, {$gym->barangay}, {$gym->city}, {$gym->province}";

        return match ($type) {
            'dti' => [
                "{$base}/dti-certificate.pdf",
                "DTI-Certificate-{$slug}.pdf",
                'application/pdf',
                $this->pdf('DTI Business Name Certificate', [
                    "Business name: {$gym->name}",
                    "Registered owner: {$ownerName}",
                    "Business address: {$address}",
                    'Certificate number: DTI-'.str_pad((string) $gym->id, 6, '0', STR_PAD_LEFT).'-2026',
                    'Validity: 31 December 2026',
                    'Demo copy generated for the Combat Platform seed data.',
                ]),
            ],
            'mayors_permit' => [
                "{$base}/mayors-permit.pdf",
                "Mayors-Permit-2026-{$slug}.pdf",
                'application/pdf',
                $this->pdf("Mayor's Business Permit", [
                    'Permit holder: '.$gym->name,
                    "Owner: {$ownerName}",
                    "Business address: {$address}",
                    'Permit number: MP-'.str_pad((string) $gym->id, 6, '0', STR_PAD_LEFT).'-2026',
                    'Valid until: 31 December 2026',
                    'Demo copy generated for the Combat Platform seed data.',
                ]),
            ],
            'barangay_clearance' => [
                "{$base}/barangay-clearance.pdf",
                "Barangay-Clearance-2026-{$slug}.pdf",
                'application/pdf',
                $this->pdf('Barangay Business Clearance', [
                    "Business name: {$gym->name}",
                    "Owner: {$ownerName}",
                    "Address: {$address}",
                    'Clearance number: BC-'.str_pad((string) $gym->id, 6, '0', STR_PAD_LEFT).'-2026',
                    'Valid until: 31 December 2026',
                    'Demo copy generated for the Combat Platform seed data.',
                ]),
            ],
            'facility_photo' => [
                "{$base}/facility-photo-{$index}.svg",
                "Facility-Photo-{$index}-{$slug}.svg",
                'image/svg+xml',
                $this->imageDocument(
                    'FACILITY PHOTO '.$index.' OF 10',
                    ($gym->facilities ?: ['Training area'])[($index - 1) % max(count($gym->facilities ?: ['Training area']), 1)],
                    $palette,
                ),
            ],
            'video_tour' => [
                "{$base}/facility-tour.mp4",
                "Facility-Tour-{$slug}.mp4",
                'video/mp4',
                $this->videoPlaceholder($gym->name),
            ],
            'owner_id' => [
                "{$base}/owner-id.pdf",
                "Owners-Valid-ID-{$slug}.pdf",
                'application/pdf',
                $this->pdf('Owner Valid ID', [
                    "Name: {$ownerName}",
                    'ID number: PH-'.str_pad((string) $owner->id, 8, '0', STR_PAD_LEFT).'-2026',
                    'Date of birth: 14 June 1988',
                    'Address: '.$gym->city.', '.$gym->province,
                    'Demo copy generated for the Combat Platform seed data.',
                ]),
            ],
            'owner_selfie' => [
                "{$base}/owner-selfie.svg",
                "Owner-Selfie-{$slug}.svg",
                'image/svg+xml',
                $this->imageDocument('OWNER SELFIE', $ownerName, $palette),
            ],
            'utility_bill' => [
                "{$base}/utility-bill.pdf",
                "Utility-Bill-October-2026-{$slug}.pdf",
                'application/pdf',
                $this->pdf('Utility Bill', [
                    'Account name: '.$gym->name,
                    "Service address: {$address}",
                    'Billing period: 1 to 31 October 2026',
                    'Amount due: PHP 18,742.65',
                    'Demo copy generated for the Combat Platform seed data.',
                ]),
            ],
            default => [
                "{$base}/{$type}.pdf",
                Str::title(str_replace('_', ' ', $type))."-{$slug}.pdf",
                'application/pdf',
                $this->pdf(Str::title(str_replace('_', ' ', $type)), [
                    "Gym: {$gym->name}",
                    "Owner: {$ownerName}",
                    'Demo copy generated for the Combat Platform seed data.',
                ]),
            ],
        };
    }

    private function imageDocument(string $eyebrow, string $title, array $palette): string
    {
        $eyebrow = $this->xml($eyebrow);
        $title = $this->xml($title);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800">
  <rect width="1200" height="800" fill="{$palette[0]}"/>
  <rect x="40" y="40" width="1120" height="720" fill="none" stroke="{$palette[2]}" stroke-width="4"/>
  <circle cx="600" cy="360" r="190" fill="{$palette[1]}" opacity="0.55"/>
  <circle cx="600" cy="300" r="92" fill="{$palette[2]}" opacity="0.35"/>
  <path d="M380 620 C450 520 540 500 600 500 C660 500 750 520 820 620" fill="none" stroke="{$palette[2]}" stroke-width="6" opacity="0.5"/>
  <text x="600" y="96" text-anchor="middle" fill="{$palette[2]}" font-family="Arial, Helvetica, sans-serif" font-size="22" letter-spacing="4">{$eyebrow}</text>
  <text x="600" y="730" text-anchor="middle" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="40" font-weight="700">{$title}</text>
</svg>
SVG;
    }

    private function videoPlaceholder(string $gymName): string
    {
        $header = hex2bin('00000018667479706d703432000000006d70343269736f6d') ?: '';

        return $header."\nCombat Platform demo video tour for {$gymName}.\n";
    }

    /** Builds a small valid one-page PDF, so seeded documents open in a PDF viewer. */
    private function pdf(string $title, array $lines): string
    {
        $escape = fn (string $text): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        $content = "BT\n/F1 20 Tf\n72 712 Td\n(".$escape($title).") Tj\nET\n";
        $y = 680;

        foreach ($lines as $line) {
            $content .= "BT\n/F1 12 Tf\n72 {$y} Td\n(".$escape($line).") Tj\nET\n";
            $y -= 22;
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @return list<array<string, mixed>> */
    private function approvedGymSpecs(): array
    {
        return [
            [
                'name' => 'Iron Will Boxing Gym',
                'owner' => 'ramon',
                'tier' => 2,
                'status' => 'approved',
                'address_line' => '24 Scout Borromeo Street',
                'barangay' => 'South Triangle',
                'city' => 'Quezon City',
                'province' => 'Metro Manila',
                'latitude' => 14.6364,
                'longitude' => 121.0339,
                'contact_phone' => '0917 823 4156',
                'contact_email' => 'frontdesk@ironwillboxing.ph',
                'operating_hours' => [
                    'mon' => ['open' => '06:00', 'close' => '21:00'],
                    'tue' => ['open' => '06:00', 'close' => '21:00'],
                    'wed' => ['open' => '06:00', 'close' => '21:00'],
                    'thu' => ['open' => '06:00', 'close' => '21:00'],
                    'fri' => ['open' => '06:00', 'close' => '21:00'],
                    'sat' => ['open' => '07:00', 'close' => '18:00'],
                    'sun' => ['open' => '08:00', 'close' => '13:00'],
                ],
                'disciplines' => ['boxing', 'muay_thai', 'kickboxing'],
                'services' => ['Boxing', 'Muay Thai', 'Kickboxing', 'Fight conditioning', 'Private coaching'],
                'facilities' => ['Boxing ring', 'Heavy bag row', 'Speed bag wall', 'Strength area', 'Locker rooms', 'Showers'],
                'activity_types' => ['classes', 'private_coaching', 'sparring', 'open_mat'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => "Members must arrive at least 15 minutes before class. Hand wraps are required on the bags and in the ring. Monthly dues are due on the 5th, and a 5% late fee applies after that. Members below 18 need a parent or guardian's consent.",
                'palette' => ['#111827', '#7f1d1d', '#f59e0b'],
                'submitted_days_ago' => 180,
                'reviewed_days_ago' => 178,
                'rooms' => ['Ring A', 'Bag Room', 'Strength Room', 'Cardio Deck'],
                'staff' => [
                    [
                        'first' => 'Bianca',
                        'last' => 'Ramos',
                        'email' => 'bianca.ramos@ironwillboxing.ph',
                        'phone' => '0917 555 0142',
                        'job_title' => 'Operations Manager',
                        'role' => 'Operations Manager',
                        'started_on' => '2023-02-15',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 45000,
                        'schedule' => [
                            'mon' => ['08:00', '17:00'],
                            'tue' => ['08:00', '17:00'],
                            'wed' => ['08:00', '17:00'],
                            'thu' => ['08:00', '17:00'],
                            'fri' => ['08:00', '17:00'],
                            'sat' => ['08:00', '12:00'],
                        ],
                    ],
                    [
                        'first' => 'Dindo',
                        'last' => 'Salazar',
                        'email' => 'dindo.salazar@ironwillboxing.ph',
                        'phone' => '0917 555 0143',
                        'job_title' => 'Head Coach',
                        'role' => 'Head Coach',
                        'started_on' => '2022-08-01',
                        'pay_basis' => 'both',
                        'monthly_rate' => 28000,
                        'per_session_rate' => 600,
                        'coach_public' => true,
                        'coach_rank' => 'ABF Lightweight Champion, 2016',
                        'coach_experience' => '18 years coaching amateur and professional boxers. He has cornered 12 national title fights.',
                        'coach_specialisation' => 'Boxing, footwork and ring craft',
                        'coach_bio' => 'Coach Dindo builds every fighter from the feet up. His classes focus on clean technique, controlled sparring and composure under pressure.',
                        'schedule' => [
                            'mon' => ['14:00', '21:00'],
                            'tue' => ['14:00', '21:00'],
                            'wed' => ['14:00', '21:00'],
                            'thu' => ['14:00', '21:00'],
                            'fri' => ['14:00', '21:00'],
                            'sat' => ['08:00', '16:00'],
                        ],
                        'leave_overrides' => ['vacation' => 20],
                    ],
                    [
                        'first' => 'Jomar',
                        'last' => 'Delos Santos',
                        'email' => 'jomar.delosantos@ironwillboxing.ph',
                        'phone' => '0917 555 0144',
                        'job_title' => 'Muay Thai Coach',
                        'role' => 'Coach',
                        'started_on' => '2024-01-10',
                        'pay_basis' => 'per_session',
                        'per_session_rate' => 500,
                        'coach_public' => true,
                        'coach_rank' => 'Kru, Muay Thai Boran',
                        'coach_experience' => '10 years competing in local and international Muay Thai events.',
                        'coach_specialisation' => 'Muay Thai clinch and elbow work',
                        'coach_bio' => 'Kru Jomar teaches traditional Muay Thai with a strong emphasis on balance, clinch control and respectful sparring.',
                        'schedule' => [
                            'tue' => ['16:00', '21:00'],
                            'wed' => ['16:00', '21:00'],
                            'thu' => ['16:00', '21:00'],
                            'fri' => ['16:00', '21:00'],
                            'sat' => ['12:00', '18:00'],
                        ],
                    ],
                    [
                        'first' => 'Patricia',
                        'last' => 'Gonzales',
                        'email' => 'trish.gonzales@ironwillboxing.ph',
                        'phone' => '0917 555 0145',
                        'job_title' => 'Front Desk Associate',
                        'role' => 'Front Desk',
                        'started_on' => '2024-06-03',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 22000,
                        'schedule' => [
                            'mon' => ['10:00', '19:00'],
                            'tue' => ['10:00', '19:00'],
                            'wed' => ['10:00', '19:00'],
                            'thu' => ['10:00', '19:00'],
                            'fri' => ['10:00', '19:00'],
                            'sat' => ['08:00', '13:00'],
                        ],
                    ],
                    [
                        'first' => 'Liza',
                        'last' => 'Fernandez',
                        'email' => 'liza.fernandez@ironwillboxing.ph',
                        'phone' => '0917 555 0146',
                        'job_title' => 'HR and Admin Officer',
                        'role' => 'HR Officer',
                        'started_on' => '2023-11-20',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 32000,
                        'schedule' => [
                            'mon' => ['09:00', '18:00'],
                            'tue' => ['09:00', '18:00'],
                            'wed' => ['09:00', '18:00'],
                            'thu' => ['09:00', '18:00'],
                            'fri' => ['09:00', '18:00'],
                        ],
                    ],
                    [
                        'first' => 'Mark Anthony',
                        'last' => 'Cruz',
                        'email' => 'mark.cruz@ironwillboxing.ph',
                        'phone' => '0917 555 0147',
                        'job_title' => 'Finance Officer',
                        'role' => 'Finance Officer',
                        'started_on' => '2024-03-04',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 35000,
                        'schedule' => [
                            'mon' => ['09:00', '18:00'],
                            'tue' => ['09:00', '18:00'],
                            'wed' => ['09:00', '18:00'],
                            'thu' => ['09:00', '18:00'],
                            'fri' => ['09:00', '18:00'],
                        ],
                    ],
                ],
                'gym_services' => [
                    [
                        'name' => 'Boxing Fundamentals',
                        'discipline' => 'boxing',
                        'activity_type' => 'classes',
                        'overview' => 'Learn stance, footwork and the jab-cross-hook combination in a coach-led group session. Every class ends with controlled pad work and a conditioning circuit. Beginners are welcome.',
                        'level' => 'beginner',
                        'duration_minutes' => 60,
                        'frequency' => 'Mon, Wed and Fri at 6:30 PM',
                        'included' => ['Coach-led warm-up and footwork drills', 'Hand-wrap instruction', 'Glove and pad work'],
                        'bring' => ['Hand wraps', 'Boxing gloves', 'Water bottle'],
                        'price' => 450,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Muay Thai Striking',
                        'discipline' => 'muay_thai',
                        'activity_type' => 'classes',
                        'overview' => 'Build powerful kicks, knees and elbows with a focus on balance and timing. The session mixes pad rounds, clinch drills and a short conditioning circuit. All levels train together with scaled intensity.',
                        'level' => 'all',
                        'duration_minutes' => 75,
                        'frequency' => 'Tue, Thu and Sat at 7:00 PM',
                        'included' => ['Pad work', 'Clinch drills', 'Conditioning circuit'],
                        'bring' => ['Shin guards', 'Muay Thai gloves', 'Mouthguard'],
                        'price' => 500,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Sparring Night',
                        'discipline' => 'boxing',
                        'activity_type' => 'sparring',
                        'overview' => 'Supervised sparring rounds for members who have completed at least three months of training. Coaches match partners by weight and experience, then review the rounds after the session. Headgear and a mouthguard are required.',
                        'level' => 'intermediate',
                        'duration_minutes' => 90,
                        'frequency' => 'Saturdays at 4:00 PM',
                        'included' => ['Supervised rounds', 'Headgear and glove check', 'Corner coaching'],
                        'bring' => ['Headgear', 'Mouthguard', 'Groin guard', 'Own gloves'],
                        'price' => 300,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Private Boxing Coaching',
                        'discipline' => 'boxing',
                        'activity_type' => 'private_coaching',
                        'overview' => 'One-on-one technical coaching built around your current level and goals. Sessions include video review, pad work and a personalised conditioning plan. Book at least one day ahead through the front desk.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'By appointment, daily',
                        'included' => ['One-on-one technical session', 'Video review', 'Personalised conditioning plan'],
                        'bring' => ['Gloves', 'Wraps', 'Training shoes'],
                        'price' => 1200,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Open Mat',
                        'discipline' => 'boxing',
                        'activity_type' => 'open_mat',
                        'overview' => 'Use the ring, bags and open floor with a coach on duty. Members can practise drills, shadow box or work with a partner at their own pace. Open mat is included in every active membership.',
                        'level' => 'all',
                        'duration_minutes' => 120,
                        'frequency' => 'Sundays at 9:00 AM',
                        'included' => ['Ring access', 'Bag access', 'Coach on the floor'],
                        'bring' => ['Gloves', 'Wraps', 'Water bottle'],
                        'price' => null,
                        'price_included' => true,
                    ],
                    [
                        'name' => 'Fight Conditioning',
                        'discipline' => 'boxing',
                        'activity_type' => 'programs',
                        'overview' => 'A six-week strength and conditioning block designed for combat athletes. Sessions combine kettlebell work, sprint intervals and core stability. Coaches adjust the load for beginners and competing fighters.',
                        'level' => 'all',
                        'duration_minutes' => 45,
                        'frequency' => 'Mon, Wed and Fri at 6:00 AM',
                        'included' => ['Coach-led warm-up', 'Kettlebell and bodyweight circuit', 'Recovery guidance'],
                        'bring' => ['Training shoes', 'Water bottle', 'Towel'],
                        'price' => 350,
                        'price_included' => false,
                    ],
                ],
                'plans' => [
                    [
                        'name' => 'Monthly Unlimited',
                        'billing_type' => 'time',
                        'duration_months' => 1,
                        'session_count' => null,
                        'total_fee' => 2800,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited group boxing and Muay Thai classes', 'Open mat access', 'Locker and shower use', 'Free hand wraps on the first visit'],
                        'policy_validity' => 'The plan starts on the first day of payment and covers one calendar month.',
                        'policy_freezing' => 'Members may freeze once per year for up to 30 days with a medical or travel reason.',
                        'policy_renewal' => 'The plan renews monthly until the member submits a cancellation form at the front desk.',
                        'policy_cancellation' => 'A cancellation needs 15 days notice before the next billing date. Classes already attended are not refunded.',
                        'addons' => [
                            ['service' => 'Private Boxing Coaching', 'price' => 1200],
                        ],
                    ],
                    [
                        'name' => '12-Session Class Pack',
                        'billing_type' => 'sessions',
                        'duration_months' => null,
                        'session_count' => 12,
                        'total_fee' => 4200,
                        'registration_included' => false,
                        'inclusions' => ['12 group classes', 'Valid for three months', 'Locker use', 'One free guest pass'],
                        'policy_validity' => 'The pack expires three months after the first class.',
                        'policy_freezing' => 'Session packs cannot be frozen, but unused sessions may be transferred once to a family member.',
                        'policy_renewal' => 'Members may buy a new pack at any time; unused sessions stay on the same account.',
                        'policy_cancellation' => 'Unused sessions are refundable within seven days of purchase with the original receipt.',
                        'addons' => [
                            ['service' => 'Private Boxing Coaching', 'price' => 1200],
                        ],
                    ],
                    [
                        'name' => 'Annual Ring Membership',
                        'billing_type' => 'time',
                        'duration_months' => 12,
                        'session_count' => null,
                        'total_fee' => 28000,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited classes for 12 months', 'Open mat and sparring nights', 'Two private coaching sessions', 'Free gym shirt', 'Priority sparring slots'],
                        'policy_validity' => 'The membership runs for 12 consecutive months from the payment date.',
                        'policy_freezing' => 'Members may freeze for up to 60 days per year in one or two blocks.',
                        'policy_renewal' => 'The membership ends automatically; members receive a renewal offer one month before expiry.',
                        'policy_cancellation' => "Early cancellation is allowed for medical reasons with a doctor's certificate. A pro-rated refund is reviewed by the owner.",
                        'addons' => [
                            ['service' => 'Private Boxing Coaching', 'price' => 1000],
                        ],
                    ],
                ],
                'holidays' => [
                    ['date' => '2026-03-15', 'name' => 'Iron Will anniversary'],
                    ['date' => '2026-08-30', 'name' => 'Barangay fiesta'],
                ],
                'leaves' => [
                    ['email' => 'bianca.ramos@ironwillboxing.ph', 'type' => 'vacation', 'starts_on' => '2026-03-16', 'ends_on' => '2026-03-18', 'reason' => 'Family trip to Palawan.', 'status' => 'approved', 'decided_days_ago' => 20],
                    ['email' => 'dindo.salazar@ironwillboxing.ph', 'type' => 'sick', 'starts_on' => '2026-06-09', 'ends_on' => '2026-06-10', 'reason' => "Flu with a doctor's advice to rest for two days.", 'status' => 'approved', 'decided_days_ago' => 25],
                    ['email' => 'jomar.delosantos@ironwillboxing.ph', 'type' => 'emergency', 'starts_on' => '2026-09-15', 'ends_on' => '2026-09-15', 'reason' => 'Flash flood at home; needed to clear the garage and help family.', 'status' => 'approved', 'decided_days_ago' => 10],
                    ['email' => 'trish.gonzales@ironwillboxing.ph', 'type' => 'vacation', 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-23', 'reason' => 'Christmas trip to the province.', 'status' => 'pending'],
                    ['email' => 'liza.fernandez@ironwillboxing.ph', 'type' => 'sick', 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-05', 'reason' => 'Clinic visit for a recurring migraine.', 'status' => 'rejected', 'decided_days_ago' => 8, 'decision_note' => 'Please file this again with a medical certificate.'],
                    ['email' => 'mark.cruz@ironwillboxing.ph', 'type' => 'service_incentive', 'starts_on' => '2026-08-20', 'ends_on' => '2026-08-20', 'reason' => 'Personal errand at the city hall.', 'status' => 'pending'],
                ],
                'members' => ['sofia', 'patricia_g', 'miguel_s', 'christian', 'alyssa'],
            ],
            [
                'name' => 'Kalaw Combat Academy',
                'owner' => 'andrea',
                'tier' => 2,
                'status' => 'approved',
                'address_line' => '118 Jupiter Street',
                'barangay' => 'Bel-Air',
                'city' => 'Makati City',
                'province' => 'Metro Manila',
                'latitude' => 14.5597,
                'longitude' => 121.0247,
                'contact_phone' => '0917 987 6543',
                'contact_email' => 'frontdesk@kalawcombat.ph',
                'operating_hours' => [
                    'mon' => ['open' => '06:00', 'close' => '22:00'],
                    'tue' => ['open' => '06:00', 'close' => '22:00'],
                    'wed' => ['open' => '06:00', 'close' => '22:00'],
                    'thu' => ['open' => '06:00', 'close' => '22:00'],
                    'fri' => ['open' => '06:00', 'close' => '22:00'],
                    'sat' => ['open' => '08:00', 'close' => '20:00'],
                    'sun' => ['open' => '09:00', 'close' => '17:00'],
                ],
                'disciplines' => ['bjj', 'mma', 'kickboxing', 'wrestling'],
                'services' => ['Brazilian jiu-jitsu', 'No-gi grappling', 'MMA', 'Wrestling', 'Private coaching'],
                'facilities' => ['200 sqm competition mat', 'Octagon cage', 'Heavy bag area', 'Strength and conditioning area', 'Locker rooms', 'Showers', 'Sauna'],
                'activity_types' => ['classes', 'programs', 'private_coaching', 'sparring', 'open_mat'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'policies' => "Respect the mats and your training partners. No shoes on the mat, and flip-flops are allowed only on the sides. Tap early and tap often. Monthly dues are due on the 1st, and cancellations need 15 days notice.",
                'palette' => ['#0f172a', '#164e63', '#22d3ee'],
                'submitted_days_ago' => 160,
                'reviewed_days_ago' => 158,
                'rooms' => ['Main Mat', 'Cage Room', 'Strength Room', 'Muay Thai Room'],
                'staff' => [
                    [
                        'first' => 'Carlo',
                        'last' => 'Dizon',
                        'email' => 'carlo.dizon@kalawcombat.ph',
                        'phone' => '0917 555 0242',
                        'job_title' => 'Operations Manager',
                        'role' => 'Operations Manager',
                        'started_on' => '2023-01-09',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 48000,
                        'schedule' => [
                            'mon' => ['08:00', '18:00'],
                            'tue' => ['08:00', '18:00'],
                            'wed' => ['08:00', '18:00'],
                            'thu' => ['08:00', '18:00'],
                            'fri' => ['08:00', '18:00'],
                        ],
                    ],
                    [
                        'first' => 'Paolo',
                        'last' => 'Gutierrez',
                        'email' => 'paolo.gutierrez@kalawcombat.ph',
                        'phone' => '0917 555 0243',
                        'job_title' => 'Head BJJ Coach',
                        'role' => 'Head Coach',
                        'started_on' => '2022-05-16',
                        'pay_basis' => 'both',
                        'monthly_rate' => 30000,
                        'per_session_rate' => 700,
                        'coach_public' => true,
                        'coach_rank' => 'BJJ Black Belt, IBJJF certified',
                        'coach_experience' => '12 years training and competing in Brazilian jiu-jitsu. He has coached medalists in local gi and no-gi tournaments.',
                        'coach_specialisation' => 'Brazilian jiu-jitsu guard game and leg locks',
                        'coach_bio' => 'Coach Paolo teaches a patient, detail-driven guard game. He believes good positions come from posture, breathing and honest training.',
                        'schedule' => [
                            'mon' => ['12:00', '21:00'],
                            'tue' => ['12:00', '21:00'],
                            'wed' => ['12:00', '21:00'],
                            'thu' => ['12:00', '21:00'],
                            'fri' => ['12:00', '21:00'],
                            'sat' => ['08:00', '14:00'],
                        ],
                        'leave_overrides' => ['vacation' => 18],
                    ],
                    [
                        'first' => 'Jonas',
                        'last' => 'Labrador',
                        'email' => 'jonas.labrador@kalawcombat.ph',
                        'phone' => '0917 555 0244',
                        'job_title' => 'MMA Coach',
                        'role' => 'Coach',
                        'started_on' => '2023-07-03',
                        'pay_basis' => 'per_session',
                        'per_session_rate' => 800,
                        'coach_public' => true,
                        'coach_rank' => 'URCC Flyweight Veteran',
                        'coach_experience' => 'Nine professional MMA fights and eight years of coaching striking and cage wrestling.',
                        'coach_specialisation' => 'MMA striking and cage wrestling',
                        'coach_bio' => 'Coach Jonas brings a calm, technical approach to a very physical sport. His sessions build confidence in the clinch and on the cage wall.',
                        'schedule' => [
                            'mon' => ['17:30', '21:30'],
                            'tue' => ['17:30', '21:30'],
                            'wed' => ['17:30', '21:30'],
                            'thu' => ['17:30', '21:30'],
                            'fri' => ['17:30', '21:30'],
                            'sat' => ['09:00', '13:00'],
                        ],
                    ],
                    [
                        'first' => 'Efren',
                        'last' => 'Tolentino',
                        'email' => 'efren.tolentino@kalawcombat.ph',
                        'phone' => '0917 555 0245',
                        'job_title' => 'Wrestling Coach',
                        'role' => 'Coach',
                        'started_on' => '2024-02-12',
                        'pay_basis' => 'per_session',
                        'per_session_rate' => 600,
                        'coach_public' => true,
                        'coach_rank' => 'UAAP Wrestling Medalist',
                        'coach_experience' => 'Seven years of collegiate wrestling and four years coaching takedowns for MMA athletes.',
                        'coach_specialisation' => 'Freestyle wrestling and takedown defence',
                        'coach_bio' => 'Coach Efren makes wrestling approachable for grapplers who have never worn a singlet. His classes focus on safe falls, angles and steady pressure.',
                        'schedule' => [
                            'tue' => ['18:00', '21:00'],
                            'thu' => ['18:00', '21:00'],
                            'sat' => ['10:00', '13:00'],
                        ],
                    ],
                    [
                        'first' => 'Kimberly',
                        'last' => 'Tan',
                        'email' => 'kimberly.tan@kalawcombat.ph',
                        'phone' => '0917 555 0246',
                        'job_title' => 'Front Desk Supervisor',
                        'role' => 'Front Desk',
                        'started_on' => '2023-03-20',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 26000,
                        'schedule' => [
                            'mon' => ['09:00', '18:00'],
                            'tue' => ['09:00', '18:00'],
                            'wed' => ['09:00', '18:00'],
                            'thu' => ['09:00', '18:00'],
                            'fri' => ['09:00', '18:00'],
                            'sat' => ['08:00', '14:00'],
                        ],
                    ],
                    [
                        'first' => 'Rafael',
                        'last' => 'Ocampo',
                        'email' => 'rafael.ocampo@kalawcombat.ph',
                        'phone' => '0917 555 0247',
                        'job_title' => 'Finance and Membership Officer',
                        'role' => 'Finance Officer',
                        'started_on' => '2022-11-07',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 34000,
                        'schedule' => [
                            'mon' => ['09:00', '18:00'],
                            'tue' => ['09:00', '18:00'],
                            'wed' => ['09:00', '18:00'],
                            'thu' => ['09:00', '18:00'],
                            'fri' => ['09:00', '18:00'],
                        ],
                    ],
                ],
                'gym_services' => [
                    [
                        'name' => 'BJJ Gi Fundamentals',
                        'discipline' => 'bjj',
                        'activity_type' => 'classes',
                        'overview' => 'Learn the core positions, escapes and submissions of Brazilian jiu-jitsu in a gi. Classes start with movement drills and finish with controlled sparring. A loaner gi is available for the first two weeks.',
                        'level' => 'beginner',
                        'duration_minutes' => 60,
                        'frequency' => 'Mon, Wed and Fri at 7:00 PM',
                        'included' => ['Loaner gi for the first two weeks', 'Technique instruction', 'Controlled sparring'],
                        'bring' => ['Gi', 'Belt', 'Water bottle', 'Flip-flops'],
                        'price' => 450,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'No-Gi Grappling',
                        'discipline' => 'bjj',
                        'activity_type' => 'classes',
                        'overview' => 'Train takedowns, guard passing and submissions without the gi. The pace is faster and the grips are different, so coach guidance is constant. Members should know the basic positions before joining.',
                        'level' => 'intermediate',
                        'duration_minutes' => 75,
                        'frequency' => 'Tue and Thu at 7:30 PM',
                        'included' => ['Technique rounds', 'Positional sparring', 'Coach feedback'],
                        'bring' => ['Rash guard', 'Grappling shorts', 'Mouthguard'],
                        'price' => 500,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'MMA Striking and Wrestling',
                        'discipline' => 'mma',
                        'activity_type' => 'classes',
                        'overview' => 'Combine boxing and kickboxing combinations with wrestling entries and cage control. Every session includes pads, a takedown drill and short situational rounds. This class is open to members with at least six months of striking or grappling experience.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'Saturdays at 10:00 AM',
                        'included' => ['Pad work', 'Takedown drills', 'Cage control practice'],
                        'bring' => ['Boxing gloves', 'Shin guards', 'Mouthguard', 'Grappling shorts'],
                        'price' => 500,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Competition Team Training',
                        'discipline' => 'mma',
                        'activity_type' => 'programs',
                        'overview' => 'A coach-invited programme for members preparing for a competition. Sessions combine strength work, sport-specific conditioning and hard sparring. The team meets before the gym opens to use the full mat and cage.',
                        'level' => 'advanced',
                        'duration_minutes' => 120,
                        'frequency' => 'Mon to Fri at 6:00 AM',
                        'included' => ['Strength and conditioning block', 'Technical rounds', 'Supervised hard sparring'],
                        'bring' => ['Competition kit', 'Mouthguard', 'Water bottle', 'Training diary'],
                        'price' => null,
                        'price_included' => true,
                    ],
                    [
                        'name' => 'Private MMA Coaching',
                        'discipline' => 'mma',
                        'activity_type' => 'private_coaching',
                        'overview' => 'A one-on-one session planned around your next fight, your weaknesses or a specific skill. The coach reviews video before the session and sends notes afterwards. Book through the front desk at least 24 hours ahead.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'By appointment, daily',
                        'included' => ['Video review', 'Personalised drill plan', 'Session notes'],
                        'bring' => ['Training kit', 'Mouthguard', 'Water bottle'],
                        'price' => 1500,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Open Mat',
                        'discipline' => 'bjj',
                        'activity_type' => 'open_mat',
                        'overview' => 'A free-training session for members of every level. Use the mat and cage to drill, flow or spar with a coach on duty. Members must bring their own gear and follow the gym sparring rules.',
                        'level' => 'all',
                        'duration_minutes' => 120,
                        'frequency' => 'Sundays at 10:00 AM',
                        'included' => ['Mat and cage access', 'Coach on duty'],
                        'bring' => ['Gi or rash guard', 'Grappling shorts', 'Water bottle'],
                        'price' => null,
                        'price_included' => true,
                    ],
                ],
                'plans' => [
                    [
                        'name' => 'Unlimited Monthly',
                        'billing_type' => 'time',
                        'duration_months' => 1,
                        'session_count' => null,
                        'total_fee' => 3200,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited gi and no-gi classes', 'Open mat access', 'Locker and shower use', 'Sauna access'],
                        'policy_validity' => 'The plan covers one calendar month from the payment date.',
                        'policy_freezing' => 'One freeze of up to 30 days per year is allowed for travel or medical reasons.',
                        'policy_renewal' => 'The plan renews monthly until a cancellation form is submitted.',
                        'policy_cancellation' => 'Cancellations need 15 days notice. The remaining days are not refunded.',
                        'addons' => [
                            ['service' => 'Private MMA Coaching', 'price' => 1500],
                        ],
                    ],
                    [
                        'name' => 'Student Monthly',
                        'billing_type' => 'time',
                        'duration_months' => 1,
                        'session_count' => null,
                        'total_fee' => 2200,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited group classes', 'Open mat access', 'Student rate with valid school ID', 'Locker use'],
                        'policy_validity' => 'The student rate applies only while a valid school ID is shown at the front desk.',
                        'policy_freezing' => 'A student member may freeze for up to 60 days during the school break.',
                        'policy_renewal' => 'The student rate is reviewed each semester.',
                        'policy_cancellation' => 'Cancellations need 15 days notice before the next billing date.',
                    ],
                    [
                        'name' => '10-Session Pass',
                        'billing_type' => 'sessions',
                        'duration_months' => null,
                        'session_count' => 10,
                        'total_fee' => 4500,
                        'registration_included' => false,
                        'inclusions' => ['10 group classes', 'Valid for four months', 'Locker use', 'One free open mat visit'],
                        'policy_validity' => 'The pass expires four months after the first class.',
                        'policy_freezing' => 'Session packs cannot be frozen, but unused sessions may be transferred once to a family member.',
                        'policy_renewal' => 'Members may buy another pass at any time; unused sessions stay on the account.',
                        'policy_cancellation' => 'Unused sessions are refundable within seven days of purchase.',
                        'addons' => [
                            ['service' => 'Private MMA Coaching', 'price' => 1500],
                        ],
                    ],
                    [
                        'name' => 'Annual Athlete Pass',
                        'billing_type' => 'time',
                        'duration_months' => 12,
                        'session_count' => null,
                        'total_fee' => 32000,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited classes for 12 months', 'Competition team tryout', 'Open mat and sauna access', 'Two private coaching sessions', 'Kalaw training shirt'],
                        'policy_validity' => 'The membership runs for 12 consecutive months from the payment date.',
                        'policy_freezing' => 'Members may freeze for up to 45 days per year with a medical reason.',
                        'policy_renewal' => 'The membership ends automatically; members receive a renewal offer one month before expiry.',
                        'policy_cancellation' => 'Early cancellation is reviewed case by case. Competition team fees are non-refundable.',
                        'addons' => [
                            ['service' => 'Private MMA Coaching', 'price' => 1300],
                        ],
                    ],
                ],
                'holidays' => [
                    ['date' => '2026-02-14', 'name' => 'Kalaw anniversary'],
                    ['date' => '2026-05-30', 'name' => 'Bel-Air neighborhood fiesta'],
                ],
                'leaves' => [
                    ['email' => 'paolo.gutierrez@kalawcombat.ph', 'type' => 'vacation', 'starts_on' => '2026-04-20', 'ends_on' => '2026-04-22', 'reason' => 'Trip to Siargao with the family.', 'status' => 'approved', 'decided_days_ago' => 30],
                    ['email' => 'jonas.labrador@kalawcombat.ph', 'type' => 'sick', 'starts_on' => '2026-07-13', 'ends_on' => '2026-07-14', 'reason' => 'Dengue symptoms; advised to rest by the clinic.', 'status' => 'approved', 'decided_days_ago' => 15],
                    ['email' => 'kimberly.tan@kalawcombat.ph', 'type' => 'vacation', 'starts_on' => '2026-12-28', 'ends_on' => '2026-12-30', 'reason' => 'Year-end holiday in Batangas.', 'status' => 'pending'],
                    ['email' => 'rafael.ocampo@kalawcombat.ph', 'type' => 'paternity', 'starts_on' => '2026-02-09', 'ends_on' => '2026-02-13', 'reason' => 'Paternity leave for the birth of our first child.', 'status' => 'approved', 'decided_days_ago' => 40],
                    ['email' => 'carlo.dizon@kalawcombat.ph', 'type' => 'emergency', 'starts_on' => '2026-11-16', 'ends_on' => '2026-11-16', 'reason' => 'Home repair after a water pipe burst.', 'status' => 'pending'],
                ],
                'members' => ['sofia', 'gabriel_tan', 'daniel', 'jasmine', 'alyssa'],
            ],
            [
                'name' => 'Bakunawa Martial Arts Center',
                'owner' => 'carlo',
                'tier' => 1,
                'status' => 'approved',
                'address_line' => '45 Osmeña Boulevard',
                'barangay' => 'Cogon Ramos',
                'city' => 'Cebu City',
                'province' => 'Cebu',
                'latitude' => 10.3135,
                'longitude' => 123.8930,
                'contact_phone' => '032 255 8841',
                'contact_email' => 'hello@bakunawama.ph',
                'operating_hours' => [
                    'mon' => ['open' => '15:00', 'close' => '21:00'],
                    'tue' => ['open' => '15:00', 'close' => '21:00'],
                    'wed' => ['open' => '15:00', 'close' => '21:00'],
                    'thu' => ['open' => '15:00', 'close' => '21:00'],
                    'fri' => ['open' => '15:00', 'close' => '21:00'],
                    'sat' => ['open' => '08:00', 'close' => '17:00'],
                    'sun' => null,
                ],
                'disciplines' => ['arnis', 'karate', 'taekwondo'],
                'services' => ['Arnis', 'Karate', 'Taekwondo', 'Private lessons'],
                'facilities' => ['Wooden training hall', 'Mirrored wall', 'Heavy bag area', 'Weapon rack', 'Changing rooms'],
                'activity_types' => ['classes', 'private_coaching', 'open_mat'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => "Training starts on time. Bring your own sticks and protective gear. Children under 12 must be accompanied by a guardian. Monthly fees are paid on or before the 10th, and the hall is closed on Sundays and national holidays.",
                'palette' => ['#1c1917', '#78350f', '#fbbf24'],
                'submitted_days_ago' => 220,
                'reviewed_days_ago' => 218,
                'rooms' => ['Main Hall', 'Weapons Room', 'Bag Area'],
                'staff' => [
                    [
                        'first' => 'Miguel',
                        'last' => 'Sarmiento',
                        'email' => 'miguel.sarmiento@bakunawama.ph',
                        'phone' => '0917 555 0342',
                        'job_title' => 'Operations Manager',
                        'role' => 'Operations Manager',
                        'started_on' => '2023-05-08',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 35000,
                        'schedule' => [
                            'mon' => ['13:00', '21:00'],
                            'tue' => ['13:00', '21:00'],
                            'wed' => ['13:00', '21:00'],
                            'thu' => ['13:00', '21:00'],
                            'fri' => ['13:00', '21:00'],
                            'sat' => ['08:00', '17:00'],
                        ],
                    ],
                    [
                        'first' => 'Dante',
                        'last' => 'Villamor',
                        'email' => 'dante.villamor@bakunawama.ph',
                        'phone' => '0917 555 0343',
                        'job_title' => 'Arnis Head Coach',
                        'role' => 'Head Coach',
                        'started_on' => '2021-09-01',
                        'pay_basis' => 'both',
                        'monthly_rate' => 22000,
                        'per_session_rate' => 400,
                        'coach_public' => true,
                        'coach_rank' => 'Grandmaster, Modern Arnis',
                        'coach_experience' => '35 years in Filipino martial arts. He is certified by the National Commission for Culture and the Arts.',
                        'coach_specialisation' => 'Arnis sticks, dagger and empty-hand techniques',
                        'coach_bio' => 'Grandmaster Dante teaches arnis as a complete fighting system. His classes pair traditional forms with practical footwork and controlled partner drills.',
                        'schedule' => [
                            'tue' => ['15:00', '21:00'],
                            'wed' => ['15:00', '21:00'],
                            'thu' => ['15:00', '21:00'],
                            'fri' => ['15:00', '21:00'],
                            'sat' => ['08:00', '17:00'],
                        ],
                        'leave_overrides' => ['vacation' => 20],
                    ],
                    [
                        'first' => 'Cristina',
                        'last' => 'Uy',
                        'email' => 'cristina.uy@bakunawama.ph',
                        'phone' => '0917 555 0344',
                        'job_title' => 'Karate and Taekwondo Coach',
                        'role' => 'Coach',
                        'started_on' => '2022-06-13',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 24000,
                        'coach_public' => true,
                        'coach_rank' => '3rd Dan Karate, 2nd Dan Taekwondo',
                        'coach_experience' => '14 years of competition and coaching in karate kata and taekwondo poomsae.',
                        'coach_specialisation' => 'Karate kata and taekwondo poomsae',
                        'coach_bio' => 'Coach Cristina is known for precise, patient instruction. She works closely with young athletes and their parents on competition readiness.',
                        'schedule' => [
                            'mon' => ['15:00', '20:00'],
                            'wed' => ['15:00', '20:00'],
                            'thu' => ['15:00', '20:00'],
                            'sat' => ['08:00', '16:00'],
                        ],
                    ],
                    [
                        'first' => 'Joel',
                        'last' => 'Bacalso',
                        'email' => 'joel.bacalso@bakunawama.ph',
                        'phone' => '0917 555 0345',
                        'job_title' => 'Assistant Arnis Coach',
                        'role' => 'Coach',
                        'started_on' => '2025-01-20',
                        'pay_basis' => 'per_session',
                        'per_session_rate' => 350,
                        'coach_public' => false,
                        'coach_rank' => 'Arnis Instructor Level 1',
                        'coach_experience' => 'Five years of arnis training and two years assisting beginner classes.',
                        'coach_specialisation' => 'Arnis fundamentals and footwork',
                        'coach_bio' => 'Coach Joel helps new students learn the basic twelve strikes and the first two arnis forms.',
                        'schedule' => [
                            'tue' => ['15:00', '20:00'],
                            'thu' => ['15:00', '20:00'],
                            'sat' => ['08:00', '13:00'],
                        ],
                    ],
                    [
                        'first' => 'Robyn',
                        'last' => 'Salcedo',
                        'email' => 'robyn.salcedo@bakunawama.ph',
                        'phone' => '0917 555 0346',
                        'job_title' => 'Front Desk Associate',
                        'role' => 'Front Desk',
                        'started_on' => '2024-08-19',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 18000,
                        'schedule' => [
                            'mon' => ['14:00', '20:00'],
                            'tue' => ['14:00', '20:00'],
                            'wed' => ['14:00', '20:00'],
                            'thu' => ['14:00', '20:00'],
                            'fri' => ['14:00', '20:00'],
                            'sat' => ['08:00', '14:00'],
                        ],
                    ],
                ],
                'gym_services' => [
                    [
                        'name' => 'Arnis Fundamentals',
                        'discipline' => 'arnis',
                        'activity_type' => 'classes',
                        'overview' => 'Learn the twelve basic strikes, blocks and footwork of Filipino arnis. Partner drills are done with padded sticks at a controlled pace. Beginners are welcome, and loaner sticks are available.',
                        'level' => 'beginner',
                        'duration_minutes' => 90,
                        'frequency' => 'Tue and Thu at 5:30 PM',
                        'included' => ['Loaner padded sticks', 'Partner drills', 'Basic forms instruction'],
                        'bring' => ['Comfortable training clothes', 'Water bottle', 'Towel'],
                        'price' => 350,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Karate Kids',
                        'discipline' => 'karate',
                        'activity_type' => 'classes',
                        'overview' => 'A fun, disciplined karate class for children aged six to twelve. The class covers basic stances, kicks, kata and respect for training partners. Parents may watch from the waiting area.',
                        'level' => 'beginner',
                        'duration_minutes' => 60,
                        'frequency' => 'Saturdays at 9:00 AM',
                        'included' => ['Uniform guidance', 'Belt preparation', 'Parent viewing area'],
                        'bring' => ['Karate gi', 'Water bottle', 'Small towel'],
                        'price' => 300,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Taekwondo Poomsae',
                        'discipline' => 'taekwondo',
                        'activity_type' => 'classes',
                        'overview' => 'Train the poomsae required for belt promotion with attention to rhythm, balance and precision. The class includes individual corrections and full-form run-throughs. Competition poomsae is offered in the advanced group.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'Mon and Wed at 6:00 PM',
                        'included' => ['Form instruction', 'Individual corrections', 'Belt promotion practice'],
                        'bring' => ['Taekwondo uniform', 'Water bottle', 'Hand towel'],
                        'price' => 350,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Private Arnis Lesson',
                        'discipline' => 'arnis',
                        'activity_type' => 'private_coaching',
                        'overview' => 'A one-on-one arnis lesson for students who want faster progress or specific weapons work. The coach plans the session around your level and goals. Book at least one day ahead at the front desk.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'By appointment, daily',
                        'included' => ['One-on-one instruction', 'Video review', 'Personal practice plan'],
                        'bring' => ['Training clothes', 'Water bottle', 'Own sticks if available'],
                        'price' => 800,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Open Floor Training',
                        'discipline' => 'arnis',
                        'activity_type' => 'open_mat',
                        'overview' => 'Use the training hall for solo forms, partner drills or light sparring with a coach present. Members bring their own sticks and protective gear. Open floor is included in every active membership.',
                        'level' => 'all',
                        'duration_minutes' => 120,
                        'frequency' => 'Saturdays at 3:00 PM',
                        'included' => ['Hall access', 'Coach on duty', 'Bag area access'],
                        'bring' => ['Sticks', 'Protective gear', 'Water bottle'],
                        'price' => null,
                        'price_included' => true,
                    ],
                ],
                'plans' => [
                    [
                        'name' => 'Monthly Regular',
                        'billing_type' => 'time',
                        'duration_months' => 1,
                        'session_count' => null,
                        'total_fee' => 1500,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited group classes', 'Open floor training', 'Locker use', 'Belt promotion assessment'],
                        'policy_validity' => 'The plan covers one calendar month from the payment date.',
                        'policy_freezing' => 'One freeze of up to 30 days per year is allowed for medical reasons.',
                        'policy_renewal' => 'The plan renews monthly until the member submits a cancellation form.',
                        'policy_cancellation' => 'A cancellation needs 10 days notice. Classes already attended are not refunded.',
                    ],
                    [
                        'name' => 'Quarterly Family Plan',
                        'billing_type' => 'time',
                        'duration_months' => 3,
                        'session_count' => null,
                        'total_fee' => 8000,
                        'registration_included' => true,
                        'inclusions' => ['Three months for two family members', 'Unlimited group classes', 'Open floor training', 'Free uniform for one child'],
                        'policy_validity' => 'The plan covers three consecutive months for the family members named on the form.',
                        'policy_freezing' => 'A family plan may be frozen once for up to 30 days with a medical reason.',
                        'policy_renewal' => 'The plan may be renewed at the family rate while all members remain active.',
                        'policy_cancellation' => 'Cancellations need 15 days notice before the next quarter.',
                        'addons' => [
                            ['service' => 'Private Arnis Lesson', 'price' => 800],
                        ],
                    ],
                    [
                        'name' => '20-Session Arnis Pass',
                        'billing_type' => 'sessions',
                        'duration_months' => null,
                        'session_count' => 20,
                        'total_fee' => 5000,
                        'registration_included' => false,
                        'inclusions' => ['20 arnis classes', 'Valid for six months', 'Loaner sticks', 'Open floor access'],
                        'policy_validity' => 'The pass expires six months after the first class.',
                        'policy_freezing' => 'Session passes cannot be frozen, but unused sessions may be transferred once to a family member.',
                        'policy_renewal' => 'Members may buy another pass at any time; unused sessions stay on the account.',
                        'policy_cancellation' => 'Unused sessions are refundable within seven days of purchase.',
                    ],
                ],
                'holidays' => [
                    ['date' => '2026-01-18', 'name' => 'Sinulog Monday - gym closed'],
                    ['date' => '2026-07-07', 'name' => 'Bakunawa founding day'],
                ],
                'leaves' => [
                    ['email' => 'cristina.uy@bakunawama.ph', 'type' => 'vacation', 'starts_on' => '2026-05-04', 'ends_on' => '2026-05-06', 'reason' => 'Short family holiday in Bohol.', 'status' => 'approved', 'decided_days_ago' => 18],
                    ['email' => 'robyn.salcedo@bakunawama.ph', 'type' => 'sick', 'starts_on' => '2026-08-10', 'ends_on' => '2026-08-10', 'reason' => 'Stomach flu; advised to rest for one day.', 'status' => 'approved', 'decided_days_ago' => 12],
                    ['email' => 'dante.villamor@bakunawama.ph', 'type' => 'bereavement', 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-17', 'reason' => 'Wake and burial of an immediate family member.', 'status' => 'approved', 'decided_days_ago' => 22],
                    ['email' => 'miguel.sarmiento@bakunawama.ph', 'type' => 'vacation', 'starts_on' => '2026-12-14', 'ends_on' => '2026-12-16', 'reason' => 'Year-end visit to family in Dumaguete.', 'status' => 'pending'],
                ],
                'members' => ['camille', 'nicole', 'jasmine'],
            ],
            [
                'name' => 'Cordillera Fight Camp',
                'owner' => 'maria',
                'tier' => 1,
                'status' => 'approved',
                'address_line' => '12 Legarda Road',
                'barangay' => 'Burnham-Legarda',
                'city' => 'Baguio City',
                'province' => 'Benguet',
                'latitude' => 16.4074,
                'longitude' => 120.5969,
                'contact_phone' => '0917 888 9900',
                'contact_email' => 'camp@cordillerafightcamp.ph',
                'operating_hours' => [
                    'mon' => ['open' => '06:00', 'close' => '20:00'],
                    'tue' => ['open' => '06:00', 'close' => '20:00'],
                    'wed' => ['open' => '06:00', 'close' => '20:00'],
                    'thu' => ['open' => '06:00', 'close' => '20:00'],
                    'fri' => ['open' => '06:00', 'close' => '20:00'],
                    'sat' => ['open' => '06:00', 'close' => '18:00'],
                    'sun' => ['open' => '07:00', 'close' => '12:00'],
                ],
                'disciplines' => ['boxing', 'mma', 'wrestling'],
                'services' => ['Boxing', 'MMA', 'Wrestling', 'Strength and conditioning', 'Private coaching'],
                'facilities' => ['Outdoor boxing ring', 'Bag room', 'Strength shed', 'Open mat area', 'Hot showers', 'Recovery room'],
                'activity_types' => ['classes', 'programs', 'private_coaching', 'sparring', 'open_mat'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => "Camp training runs outdoors unless the weather is unsafe. Bring a water bottle, gloves and proper shoes. Monthly dues are paid on the 1st, and sparring is never allowed without a coach present. Members under 18 need a guardian's consent.",
                'palette' => ['#1e293b', '#14532d', '#84cc16'],
                'submitted_days_ago' => 140,
                'reviewed_days_ago' => 138,
                'rooms' => ['Outdoor Ring', 'Bag Room', 'Strength Shed', 'Mat Area'],
                'staff' => [
                    [
                        'first' => 'Althea',
                        'last' => 'Padilla',
                        'email' => 'althea.padilla@cordillerafightcamp.ph',
                        'phone' => '0917 555 0442',
                        'job_title' => 'Camp Manager',
                        'role' => 'Operations Manager',
                        'started_on' => '2023-06-12',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 38000,
                        'schedule' => [
                            'mon' => ['07:00', '17:00'],
                            'tue' => ['07:00', '17:00'],
                            'wed' => ['07:00', '17:00'],
                            'thu' => ['07:00', '17:00'],
                            'fri' => ['07:00', '17:00'],
                            'sat' => ['07:00', '13:00'],
                        ],
                    ],
                    [
                        'first' => 'Ben',
                        'last' => 'Tolentino',
                        'email' => 'ben.tolentino@cordillerafightcamp.ph',
                        'phone' => '0917 555 0443',
                        'job_title' => 'Head Coach',
                        'role' => 'Head Coach',
                        'started_on' => '2022-10-03',
                        'pay_basis' => 'both',
                        'monthly_rate' => 26000,
                        'per_session_rate' => 500,
                        'coach_public' => true,
                        'coach_rank' => 'Wushu Sanda National Team, 2014',
                        'coach_experience' => '15 years of competition and coaching in wushu, MMA and wrestling.',
                        'coach_specialisation' => 'MMA, wrestling and clinch work',
                        'coach_bio' => 'Coach Ben likes simple, repeatable technique. His fighters learn to control the clinch first and add strikes once their base is solid.',
                        'schedule' => [
                            'mon' => ['15:00', '20:00'],
                            'tue' => ['15:00', '20:00'],
                            'wed' => ['15:00', '20:00'],
                            'thu' => ['15:00', '20:00'],
                            'fri' => ['15:00', '20:00'],
                            'sat' => ['07:00', '12:00'],
                        ],
                        'leave_overrides' => ['vacation' => 18],
                    ],
                    [
                        'first' => 'Samuel',
                        'last' => 'Bumanglag',
                        'email' => 'samuel.bumanglag@cordillerafightcamp.ph',
                        'phone' => '0917 555 0444',
                        'job_title' => 'Boxing Coach',
                        'role' => 'Coach',
                        'started_on' => '2023-03-06',
                        'pay_basis' => 'per_session',
                        'per_session_rate' => 450,
                        'coach_public' => true,
                        'coach_rank' => 'Philippine Golden Gloves finalist, 2018',
                        'coach_experience' => 'Eight years of amateur boxing and five years of coaching at the camp.',
                        'coach_specialisation' => 'Boxing conditioning and bag work',
                        'coach_bio' => 'Coach Samuel is the camp early-morning coach. He is known for sharp pad work and for making hard conditioning sessions feel achievable.',
                        'schedule' => [
                            'mon' => ['06:00', '09:00'],
                            'tue' => ['06:00', '09:00'],
                            'wed' => ['06:00', '09:00'],
                            'thu' => ['06:00', '09:00'],
                            'fri' => ['06:00', '09:00'],
                            'sat' => ['07:00', '11:00'],
                        ],
                    ],
                    [
                        'first' => 'Joy',
                        'last' => 'Aban',
                        'email' => 'joy.aban@cordillerafightcamp.ph',
                        'phone' => '0917 555 0445',
                        'job_title' => 'Front Desk and Camp Coordinator',
                        'role' => 'Front Desk',
                        'started_on' => '2024-04-15',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 20000,
                        'schedule' => [
                            'mon' => ['08:00', '17:00'],
                            'tue' => ['08:00', '17:00'],
                            'wed' => ['08:00', '17:00'],
                            'thu' => ['08:00', '17:00'],
                            'fri' => ['08:00', '17:00'],
                            'sat' => ['07:00', '12:00'],
                        ],
                    ],
                ],
                'gym_services' => [
                    [
                        'name' => 'Boxing Conditioning',
                        'discipline' => 'boxing',
                        'activity_type' => 'classes',
                        'overview' => 'Start the day with roadwork-style intervals, bag rounds and core work. The session is built for fighters who need stamina without heavy sparring. All levels work at their own pace with a coach watching form.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'Mon, Wed and Fri at 6:00 AM',
                        'included' => ['Warm-up and mobility', 'Bag rounds', 'Core and stretching'],
                        'bring' => ['Running shoes', 'Gloves', 'Water bottle', 'Towel'],
                        'price' => 350,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Wrestling Takedown Clinic',
                        'discipline' => 'wrestling',
                        'activity_type' => 'classes',
                        'overview' => 'Learn single-leg, double-leg and upper-body takedowns with safe breakfall practice. The clinic focuses on setup, balance and finishing cleanly. Members should have at least three months of grappling experience.',
                        'level' => 'intermediate',
                        'duration_minutes' => 75,
                        'frequency' => 'Tue and Thu at 6:30 PM',
                        'included' => ['Technique instruction', 'Breakfall practice', 'Situational rounds'],
                        'bring' => ['Rash guard', 'Grappling shorts', 'Mouthguard'],
                        'price' => 400,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'MMA Fundamentals',
                        'discipline' => 'mma',
                        'activity_type' => 'classes',
                        'overview' => 'Build a base in stance, movement, basic striking and takedown defence. The class keeps sparring light and focuses on clean technique. New members are paired with an experienced training partner.',
                        'level' => 'beginner',
                        'duration_minutes' => 75,
                        'frequency' => 'Saturdays at 9:00 AM',
                        'included' => ['Striking fundamentals', 'Takedown defence', 'Light technical sparring'],
                        'bring' => ['Boxing gloves', 'Shin guards', 'Mouthguard', 'Water bottle'],
                        'price' => 450,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Private Strength Coaching',
                        'discipline' => 'mma',
                        'activity_type' => 'private_coaching',
                        'overview' => 'A personalised strength and conditioning session for combat athletes. The coach assesses your movement, then builds a six-week plan around your competition schedule. Sessions are held in the strength shed or outdoors.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'By appointment, daily',
                        'included' => ['Movement assessment', 'Six-week training plan', 'Progress check-in'],
                        'bring' => ['Training shoes', 'Water bottle', 'Training diary'],
                        'price' => 900,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Open Mat',
                        'discipline' => 'mma',
                        'activity_type' => 'open_mat',
                        'overview' => 'A relaxed Sunday session for drilling, flow rolling and light sparring. A coach is on the floor to keep rounds safe and answer questions. Open mat is included in every active membership.',
                        'level' => 'all',
                        'duration_minutes' => 120,
                        'frequency' => 'Sundays at 8:00 AM',
                        'included' => ['Mat and bag access', 'Coach on duty'],
                        'bring' => ['Training kit', 'Water bottle', 'Mouthguard'],
                        'price' => null,
                        'price_included' => true,
                    ],
                ],
                'plans' => [
                    [
                        'name' => 'Camp Monthly',
                        'billing_type' => 'time',
                        'duration_months' => 1,
                        'session_count' => null,
                        'total_fee' => 2500,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited group classes', 'Open mat access', 'Hot showers', 'Locker use'],
                        'policy_validity' => 'The plan covers one calendar month from the payment date.',
                        'policy_freezing' => 'Members may freeze once per year for up to 30 days with a medical reason.',
                        'policy_renewal' => 'The plan renews monthly until a cancellation form is submitted.',
                        'policy_cancellation' => 'Cancellations need 15 days notice before the next billing date.',
                        'addons' => [
                            ['service' => 'Private Strength Coaching', 'price' => 900],
                        ],
                    ],
                    [
                        'name' => 'Drop-in 8-Session Pass',
                        'billing_type' => 'sessions',
                        'duration_months' => null,
                        'session_count' => 8,
                        'total_fee' => 2800,
                        'registration_included' => false,
                        'inclusions' => ['Eight group classes', 'Valid for two months', 'Open mat access', 'Hot showers'],
                        'policy_validity' => 'The pass expires two months after the first class.',
                        'policy_freezing' => 'Session passes cannot be frozen, but unused sessions may be transferred once to a family member.',
                        'policy_renewal' => 'Members may buy another pass at any time.',
                        'policy_cancellation' => 'Unused sessions are refundable within seven days of purchase.',
                    ],
                    [
                        'name' => 'Annual Camp Pass',
                        'billing_type' => 'time',
                        'duration_months' => 12,
                        'session_count' => null,
                        'total_fee' => 24000,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited classes for 12 months', 'Open mat and sparring sessions', 'Two private coaching sessions', 'Camp shirt', 'Priority booking for camps'],
                        'policy_validity' => 'The membership runs for 12 consecutive months from the payment date.',
                        'policy_freezing' => 'Members may freeze for up to 45 days per year with a medical reason.',
                        'policy_renewal' => 'The membership ends automatically; members receive a renewal offer one month before expiry.',
                        'policy_cancellation' => 'Early cancellation is reviewed by the owner. A pro-rated refund may be given for medical reasons.',
                        'addons' => [
                            ['service' => 'Private Strength Coaching', 'price' => 800],
                        ],
                    ],
                ],
                'holidays' => [
                    ['date' => '2026-05-15', 'name' => 'Cordillera Fight Camp anniversary'],
                    ['date' => '2026-09-01', 'name' => 'Baguio Day'],
                ],
                'leaves' => [
                    ['email' => 'ben.tolentino@cordillerafightcamp.ph', 'type' => 'vacation', 'starts_on' => '2026-03-23', 'ends_on' => '2026-03-25', 'reason' => 'Visiting family in Ifugao.', 'status' => 'approved', 'decided_days_ago' => 21],
                    ['email' => 'samuel.bumanglag@cordillerafightcamp.ph', 'type' => 'sick', 'starts_on' => '2026-09-07', 'ends_on' => '2026-09-07', 'reason' => 'Fever and sore throat; clinic advised one day of rest.', 'status' => 'approved', 'decided_days_ago' => 9],
                    ['email' => 'althea.padilla@cordillerafightcamp.ph', 'type' => 'emergency', 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-23', 'reason' => 'Emergency plumbing repair at home.', 'status' => 'pending'],
                ],
                'members' => ['patricia_g', 'miguel_s', 'anthony'],
            ],
            [
                'name' => 'Davao Grappling Club',
                'owner' => 'paolo',
                'tier' => 2,
                'status' => 'approved',
                'address_line' => '88 Matina Crossing Road',
                'barangay' => 'Matina Crossing',
                'city' => 'Davao City',
                'province' => 'Davao del Sur',
                'latitude' => 7.0635,
                'longitude' => 125.6045,
                'contact_phone' => '0920 888 7766',
                'contact_email' => 'frontdesk@davaograppling.ph',
                'operating_hours' => [
                    'mon' => ['open' => '07:00', 'close' => '22:00'],
                    'tue' => ['open' => '07:00', 'close' => '22:00'],
                    'wed' => ['open' => '07:00', 'close' => '22:00'],
                    'thu' => ['open' => '07:00', 'close' => '22:00'],
                    'fri' => ['open' => '07:00', 'close' => '22:00'],
                    'sat' => ['open' => '08:00', 'close' => '18:00'],
                    'sun' => ['open' => '09:00', 'close' => '13:00'],
                ],
                'disciplines' => ['bjj', 'wrestling', 'mma'],
                'services' => ['Brazilian jiu-jitsu', 'No-gi grappling', 'Wrestling', 'MMA', 'Private coaching'],
                'facilities' => ['200 sqm competition mat', 'Octagon cage', 'Heavy bag area', 'Strength area', 'Showers', 'Physiotherapy room'],
                'activity_types' => ['classes', 'programs', 'private_coaching', 'sparring', 'open_mat'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => "All members must sign the mat waiver before their first class. No shoes on the mat. Sparring is coach-supervised and requires a mouthguard and groin guard. Monthly dues are due on the 5th, and one 30-day freeze is allowed per year.",
                'palette' => ['#0c0a09', '#3f1d38', '#f472b6'],
                'submitted_days_ago' => 120,
                'reviewed_days_ago' => 118,
                'rooms' => ['Main Mat', 'Cage Room', 'Strength Room', 'Treatment Room'],
                'staff' => [
                    [
                        'first' => 'Nico',
                        'last' => 'Alcantara',
                        'email' => 'nico.alcantara@davaograppling.ph',
                        'phone' => '0917 555 0542',
                        'job_title' => 'Operations Manager',
                        'role' => 'Operations Manager',
                        'started_on' => '2023-04-17',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 42000,
                        'schedule' => [
                            'mon' => ['08:00', '18:00'],
                            'tue' => ['08:00', '18:00'],
                            'wed' => ['08:00', '18:00'],
                            'thu' => ['08:00', '18:00'],
                            'fri' => ['08:00', '18:00'],
                            'sat' => ['08:00', '13:00'],
                        ],
                    ],
                    [
                        'first' => 'Gabriel',
                        'last' => 'Sison',
                        'email' => 'gabriel.sison@davaograppling.ph',
                        'phone' => '0917 555 0543',
                        'job_title' => 'Head BJJ Coach',
                        'role' => 'Head Coach',
                        'started_on' => '2022-03-14',
                        'pay_basis' => 'both',
                        'monthly_rate' => 30000,
                        'per_session_rate' => 650,
                        'coach_public' => true,
                        'coach_rank' => 'BJJ Black Belt, IBJJF certified',
                        'coach_experience' => '14 years in Brazilian jiu-jitsu and six years coaching competition teams in Mindanao.',
                        'coach_specialisation' => 'BJJ guard game and leg locks',
                        'coach_bio' => 'Coach Gabriel is a detail-oriented teacher who builds strong fundamentals before adding advanced attacks. He is known for a calm, welcoming mat culture.',
                        'schedule' => [
                            'mon' => ['12:00', '21:00'],
                            'tue' => ['12:00', '21:00'],
                            'wed' => ['12:00', '21:00'],
                            'thu' => ['12:00', '21:00'],
                            'fri' => ['12:00', '21:00'],
                            'sat' => ['09:00', '15:00'],
                        ],
                        'leave_overrides' => ['vacation' => 18],
                    ],
                    [
                        'first' => 'Marlon',
                        'last' => 'Tiu',
                        'email' => 'marlon.tiu@davaograppling.ph',
                        'phone' => '0917 555 0544',
                        'job_title' => 'Wrestling Coach',
                        'role' => 'Coach',
                        'started_on' => '2023-08-07',
                        'pay_basis' => 'per_session',
                        'per_session_rate' => 550,
                        'coach_public' => true,
                        'coach_rank' => 'UAAP Wrestling Medalist',
                        'coach_experience' => 'Six years of collegiate wrestling and three years coaching takedowns for MMA and grappling athletes.',
                        'coach_specialisation' => 'Freestyle and Greco-Roman wrestling',
                        'coach_bio' => 'Coach Marlon makes takedowns simple and safe. His sessions are built around stance, motion and finishing cleanly.',
                        'schedule' => [
                            'tue' => ['18:00', '21:00'],
                            'thu' => ['18:00', '21:00'],
                            'sat' => ['10:00', '14:00'],
                        ],
                    ],
                    [
                        'first' => 'Charmaine',
                        'last' => 'Go',
                        'email' => 'charmaine.go@davaograppling.ph',
                        'phone' => '0917 555 0545',
                        'job_title' => 'Front Desk Associate',
                        'role' => 'Front Desk',
                        'started_on' => '2024-09-02',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 21000,
                        'schedule' => [
                            'mon' => ['09:00', '18:00'],
                            'tue' => ['09:00', '18:00'],
                            'wed' => ['09:00', '18:00'],
                            'thu' => ['09:00', '18:00'],
                            'fri' => ['09:00', '18:00'],
                            'sat' => ['08:00', '14:00'],
                        ],
                    ],
                    [
                        'first' => 'Elena',
                        'last' => 'Vasquez',
                        'email' => 'elena.vasquez@davaograppling.ph',
                        'phone' => '0917 555 0546',
                        'job_title' => 'Sports Therapist',
                        'role' => 'Sports Therapist',
                        'started_on' => '2024-01-15',
                        'pay_basis' => 'monthly',
                        'monthly_rate' => 30000,
                        'coach_public' => false,
                        'coach_experience' => 'Licensed physiotherapist with five years working with combat athletes.',
                        'coach_specialisation' => 'Injury screening and return-to-training plans',
                        'coach_bio' => 'Elena runs the treatment room and supports members returning from injury. She works with coaches to adjust training loads.',
                        'schedule' => [
                            'tue' => ['09:00', '18:00'],
                            'wed' => ['09:00', '18:00'],
                            'thu' => ['09:00', '18:00'],
                            'fri' => ['09:00', '18:00'],
                            'sat' => ['09:00', '13:00'],
                        ],
                    ],
                ],
                'gym_services' => [
                    [
                        'name' => 'BJJ Gi Fundamentals',
                        'discipline' => 'bjj',
                        'activity_type' => 'classes',
                        'overview' => 'Learn the basic positions, escapes and submissions of Brazilian jiu-jitsu in a gi. Sessions include movement drills, technique and controlled rounds. Loaner gis are available for the first two weeks.',
                        'level' => 'beginner',
                        'duration_minutes' => 60,
                        'frequency' => 'Mon, Wed and Fri at 7:00 PM',
                        'included' => ['Loaner gi for the first two weeks', 'Technique instruction', 'Controlled rounds'],
                        'bring' => ['Gi', 'Belt', 'Water bottle', 'Flip-flops'],
                        'price' => 400,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'No-Gi Grappling',
                        'discipline' => 'bjj',
                        'activity_type' => 'classes',
                        'overview' => 'Train takedowns, guard passing and submissions without the gi. The class moves at a faster pace and uses a different set of grips. Members should know the basic positions before joining.',
                        'level' => 'intermediate',
                        'duration_minutes' => 75,
                        'frequency' => 'Tue and Thu at 7:30 PM',
                        'included' => ['Technique rounds', 'Positional sparring', 'Coach feedback'],
                        'bring' => ['Rash guard', 'Grappling shorts', 'Mouthguard'],
                        'price' => 450,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Wrestling for MMA',
                        'discipline' => 'wrestling',
                        'activity_type' => 'classes',
                        'overview' => 'Learn the takedowns, sprawls and cage wrestling that work in MMA. Every session includes a technical block and short situational rounds. The coach matches partners by size and experience.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'Saturdays at 10:00 AM',
                        'included' => ['Takedown instruction', 'Cage drills', 'Situational rounds'],
                        'bring' => ['Rash guard', 'Grappling shorts', 'Mouthguard'],
                        'price' => 450,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Competition Training',
                        'discipline' => 'mma',
                        'activity_type' => 'programs',
                        'overview' => 'A coach-invited programme for members preparing for a grappling or MMA competition. Sessions combine hard rounds, strength work and game-plan review. The team meets before the gym opens.',
                        'level' => 'advanced',
                        'duration_minutes' => 120,
                        'frequency' => 'Mon to Fri at 6:00 AM',
                        'included' => ['Hard rounds', 'Strength and conditioning', 'Game-plan review'],
                        'bring' => ['Competition kit', 'Mouthguard', 'Water bottle', 'Training diary'],
                        'price' => null,
                        'price_included' => true,
                    ],
                    [
                        'name' => 'Private Grappling Coaching',
                        'discipline' => 'bjj',
                        'activity_type' => 'private_coaching',
                        'overview' => 'A one-on-one session built around your game, your next competition or a stubborn weakness. The coach reviews video before the session and sends notes afterwards. Book at least one day ahead through the front desk.',
                        'level' => 'all',
                        'duration_minutes' => 60,
                        'frequency' => 'By appointment, daily',
                        'included' => ['Video review', 'Personalised drill plan', 'Session notes'],
                        'bring' => ['Gi or rash guard', 'Grappling shorts', 'Water bottle'],
                        'price' => 1300,
                        'price_included' => false,
                    ],
                    [
                        'name' => 'Open Mat',
                        'discipline' => 'bjj',
                        'activity_type' => 'open_mat',
                        'overview' => 'A free-training session for members of every level. Use the mat and cage to drill, flow or spar with a coach on duty. Open mat is included in every active membership.',
                        'level' => 'all',
                        'duration_minutes' => 120,
                        'frequency' => 'Sundays at 10:00 AM',
                        'included' => ['Mat and cage access', 'Coach on duty'],
                        'bring' => ['Gi or rash guard', 'Grappling shorts', 'Water bottle'],
                        'price' => null,
                        'price_included' => true,
                    ],
                ],
                'plans' => [
                    [
                        'name' => 'Unlimited Monthly',
                        'billing_type' => 'time',
                        'duration_months' => 1,
                        'session_count' => null,
                        'total_fee' => 2800,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited gi and no-gi classes', 'Open mat access', 'Locker and shower use', 'Physiotherapy screening'],
                        'policy_validity' => 'The plan covers one calendar month from the payment date.',
                        'policy_freezing' => 'One freeze of up to 30 days per year is allowed for travel or medical reasons.',
                        'policy_renewal' => 'The plan renews monthly until a cancellation form is submitted.',
                        'policy_cancellation' => 'Cancellations need 15 days notice. Classes already attended are not refunded.',
                        'addons' => [
                            ['service' => 'Private Grappling Coaching', 'price' => 1300],
                        ],
                    ],
                    [
                        'name' => '12-Session Pass',
                        'billing_type' => 'sessions',
                        'duration_months' => null,
                        'session_count' => 12,
                        'total_fee' => 3800,
                        'registration_included' => false,
                        'inclusions' => ['12 group classes', 'Valid for three months', 'Open mat access', 'Locker use'],
                        'policy_validity' => 'The pass expires three months after the first class.',
                        'policy_freezing' => 'Session packs cannot be frozen, but unused sessions may be transferred once to a family member.',
                        'policy_renewal' => 'Members may buy another pass at any time; unused sessions stay on the account.',
                        'policy_cancellation' => 'Unused sessions are refundable within seven days of purchase.',
                    ],
                    [
                        'name' => 'Annual Grappling Pass',
                        'billing_type' => 'time',
                        'duration_months' => 12,
                        'session_count' => null,
                        'total_fee' => 26000,
                        'registration_included' => true,
                        'inclusions' => ['Unlimited classes for 12 months', 'Open mat and competition training', 'Two private coaching sessions', 'Club rash guard', 'Physiotherapy screening'],
                        'policy_validity' => 'The membership runs for 12 consecutive months from the payment date.',
                        'policy_freezing' => 'Members may freeze for up to 45 days per year with a medical reason.',
                        'policy_renewal' => 'The membership ends automatically; members receive a renewal offer one month before expiry.',
                        'policy_cancellation' => 'Early cancellation is reviewed case by case. Competition team fees are non-refundable.',
                        'addons' => [
                            ['service' => 'Private Grappling Coaching', 'price' => 1200],
                        ],
                    ],
                ],
                'holidays' => [
                    ['date' => '2026-03-16', 'name' => 'Araw ng Dabaw'],
                    ['date' => '2026-08-22', 'name' => 'Kadayawan weekend'],
                ],
                'leaves' => [
                    ['email' => 'gabriel.sison@davaograppling.ph', 'type' => 'vacation', 'starts_on' => '2026-04-27', 'ends_on' => '2026-04-29', 'reason' => 'Family holiday in Samal.', 'status' => 'approved', 'decided_days_ago' => 24],
                    ['email' => 'marlon.tiu@davaograppling.ph', 'type' => 'sick', 'starts_on' => '2026-08-31', 'ends_on' => '2026-09-01', 'reason' => 'Sprained ankle; clinic advised two days off the mat.', 'status' => 'approved', 'decided_days_ago' => 11],
                    ['email' => 'charmaine.go@davaograppling.ph', 'type' => 'vacation', 'starts_on' => '2026-12-21', 'ends_on' => '2026-12-22', 'reason' => 'Christmas break with family in General Santos.', 'status' => 'pending'],
                    ['email' => 'elena.vasquez@davaograppling.ph', 'type' => 'service_incentive', 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-20', 'reason' => 'Personal matters and a medical appointment.', 'status' => 'approved', 'decided_days_ago' => 14],
                    ['email' => 'nico.alcantara@davaograppling.ph', 'type' => 'emergency', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'reason' => 'School emergency for my child.', 'status' => 'pending'],
                ],
                'members' => ['gabriel_tan', 'daniel', 'jasmine', 'anthony'],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function applicationSpecs(): array
    {
        return [
            [
                'name' => 'Southside Muay Thai',
                'owner' => 'jomar_aquino',
                'tier' => 1,
                'status' => 'submitted',
                'address_line' => '12 Real Street',
                'barangay' => 'Talon Dos',
                'city' => 'Las Piñas City',
                'province' => 'Metro Manila',
                'latitude' => 14.4445,
                'longitude' => 120.9935,
                'contact_phone' => '0910 345 6789',
                'contact_email' => 'hello@southsidemuaythai.ph',
                'operating_hours' => [
                    'mon' => ['open' => '10:00', 'close' => '21:00'],
                    'tue' => ['open' => '10:00', 'close' => '21:00'],
                    'wed' => ['open' => '10:00', 'close' => '21:00'],
                    'thu' => ['open' => '10:00', 'close' => '21:00'],
                    'fri' => ['open' => '10:00', 'close' => '21:00'],
                    'sat' => ['open' => '08:00', 'close' => '18:00'],
                    'sun' => null,
                ],
                'disciplines' => ['muay_thai', 'boxing'],
                'services' => ['Muay Thai', 'Boxing', 'Strength and conditioning'],
                'facilities' => ['Boxing ring', 'Bag area', 'Showers'],
                'activity_types' => ['classes', 'private_coaching', 'sparring'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => 'Arrive 15 minutes before class with clean wraps and gloves. Sparring is allowed only after a coach clears the member. Monthly dues are paid on the 5th.',
                'palette' => ['#0f172a', '#7c2d12', '#fb923c'],
                'submitted_days_ago' => 3,
            ],
            [
                'name' => 'Visayas Fight Lab',
                'owner' => 'grace',
                'tier' => 2,
                'status' => 'under_review',
                'address_line' => '22 Boardwalk Avenue',
                'barangay' => 'San Rafael',
                'city' => 'Iloilo City',
                'province' => 'Iloilo',
                'latitude' => 10.7148,
                'longitude' => 122.5627,
                'contact_phone' => '0917 123 9876',
                'contact_email' => 'team@visayasfightlab.ph',
                'operating_hours' => [
                    'mon' => ['open' => '06:00', 'close' => '21:00'],
                    'tue' => ['open' => '06:00', 'close' => '21:00'],
                    'wed' => ['open' => '06:00', 'close' => '21:00'],
                    'thu' => ['open' => '06:00', 'close' => '21:00'],
                    'fri' => ['open' => '06:00', 'close' => '21:00'],
                    'sat' => ['open' => '06:00', 'close' => '18:00'],
                    'sun' => ['open' => '08:00', 'close' => '12:00'],
                ],
                'disciplines' => ['mma', 'boxing', 'bjj', 'wrestling'],
                'services' => ['MMA', 'Boxing', 'Brazilian jiu-jitsu', 'Wrestling'],
                'facilities' => ['Octagon cage', 'Competition mat', 'Bag area', 'Strength area', 'Showers'],
                'activity_types' => ['classes', 'programs', 'private_coaching', 'sparring'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => 'No shoes on the mat. Sparring requires a mouthguard and groin guard. Monthly dues are due on the 1st. Competition team training is by coach invitation.',
                'palette' => ['#111827', '#1e3a8a', '#60a5fa'],
                'submitted_days_ago' => 6,
                'checklist' => [
                    'documents_complete' => true,
                    'permits_current' => true,
                    'details_match' => true,
                    'selfie_matches_id' => false,
                    'facility_matches' => false,
                ],
            ],
            [
                'name' => 'Tagum Boxing Club',
                'owner' => 'nestor',
                'tier' => 1,
                'status' => 'returned',
                'address_line' => '55 Pioneer Avenue',
                'barangay' => 'Magugpo Poblacion',
                'city' => 'Tagum City',
                'province' => 'Davao del Norte',
                'latitude' => 7.4478,
                'longitude' => 125.8078,
                'contact_phone' => '0927 345 6781',
                'contact_email' => 'coach.nestor@tagumboxing.ph',
                'operating_hours' => [
                    'mon' => ['open' => '06:00', 'close' => '20:00'],
                    'tue' => ['open' => '06:00', 'close' => '20:00'],
                    'wed' => ['open' => '06:00', 'close' => '20:00'],
                    'thu' => ['open' => '06:00', 'close' => '20:00'],
                    'fri' => ['open' => '06:00', 'close' => '20:00'],
                    'sat' => ['open' => '06:00', 'close' => '17:00'],
                    'sun' => null,
                ],
                'disciplines' => ['boxing'],
                'services' => ['Boxing', 'Boxing fitness'],
                'facilities' => ['Boxing ring', 'Bag area'],
                'activity_types' => ['classes', 'private_coaching'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => 'Wraps are required on the bags and in the ring. Young boxers must be accompanied by a guardian. Monthly dues are paid on the 10th.',
                'palette' => ['#171717', '#7f1d1d', '#f97316'],
                'submitted_days_ago' => 8,
                'reviewed_days_ago' => 2,
                'return_items' => [
                    'Upload a current Mayor\'s permit. The one on file expired on 30 September 2026.',
                    'The barangay clearance shows a different street address.',
                ],
                'decision_reason' => 'Please fix the two items and send the application again.',
            ],
            [
                'name' => 'Mindoro Combat Sports',
                'owner' => 'rebecca',
                'tier' => 1,
                'status' => 'rejected',
                'address_line' => '8 J.P. Rizal Street',
                'barangay' => 'San Vicente',
                'city' => 'Calapan City',
                'province' => 'Oriental Mindoro',
                'latitude' => 13.4117,
                'longitude' => 121.1803,
                'contact_phone' => '0915 467 8901',
                'contact_email' => 'info@mindorocombat.ph',
                'operating_hours' => [
                    'mon' => ['open' => '07:00', 'close' => '20:00'],
                    'tue' => ['open' => '07:00', 'close' => '20:00'],
                    'wed' => ['open' => '07:00', 'close' => '20:00'],
                    'thu' => ['open' => '07:00', 'close' => '20:00'],
                    'fri' => ['open' => '07:00', 'close' => '20:00'],
                    'sat' => ['open' => '07:00', 'close' => '17:00'],
                    'sun' => null,
                ],
                'disciplines' => ['boxing', 'arnis'],
                'services' => ['Boxing', 'Arnis'],
                'facilities' => ['Boxing ring', 'Training hall'],
                'activity_types' => ['classes', 'private_coaching'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => 'Training starts on time. Bring your own wraps and sticks. Monthly dues are paid on the 5th.',
                'palette' => ['#1e1b4b', '#831843', '#f9a8d4'],
                'submitted_days_ago' => 12,
                'reviewed_days_ago' => 5,
                'decision_reason' => 'The business name on the DTI certificate does not match the gym name or the Mayor\'s permit.',
            ],
            [
                'name' => 'Northside Boxing Studio',
                'owner' => 'luis',
                'tier' => 1,
                'status' => 'draft',
                'address_line' => '31 MacArthur Highway',
                'barangay' => 'Karuhatan',
                'city' => 'Valenzuela City',
                'province' => 'Metro Manila',
                'latitude' => 14.7011,
                'longitude' => 120.9830,
                'contact_phone' => '0916 555 1234',
                'contact_email' => 'luis.herrera@gmail.com',
                'operating_hours' => [
                    'mon' => ['open' => '09:00', 'close' => '20:00'],
                    'tue' => ['open' => '09:00', 'close' => '20:00'],
                    'wed' => ['open' => '09:00', 'close' => '20:00'],
                    'thu' => ['open' => '09:00', 'close' => '20:00'],
                    'fri' => ['open' => '09:00', 'close' => '20:00'],
                    'sat' => ['open' => '08:00', 'close' => '17:00'],
                    'sun' => null,
                ],
                'disciplines' => ['boxing'],
                'services' => ['Boxing'],
                'facilities' => ['Boxing ring', 'Bag area'],
                'activity_types' => ['classes'],
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'policies' => null,
                'palette' => ['#1f2937', '#374151', '#eab308'],
            ],
        ];
    }
}
