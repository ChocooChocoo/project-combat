<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\GymStatus;
use Illuminate\Support\Facades\DB;
use App\Models\AthleteProfile;
use App\Models\Gym;
use App\Enums\CustomerSource;
use App\Models\Customer;
use App\Models\GymFile;
use App\Models\GymRole;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\WalkIn;
use App\Notifications\GymNotice;
use App\Support\Finance\SaleRecorder;
use App\Models\Membership;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\PayrollLock;
use App\Support\StaffAttendanceBook;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** P10 and P11 demo records: gov IDs, clock-in location, thumbnails, gym media, hiring, walk-ins, sparring, identities. */
trait P10P11Scenarios
{
    /** One call per approved gym, after P09 data is seeded. */
    private function seedP10P11(Gym $gym, User $owner, array $employees): void
    {
        $active = $gym->memberships()->with('user')->where('status', 'active')->orderBy('id')->get();
        $this->seedGovernmentIds($gym, $employees);   // (R-121)
        $this->seedClockInLocations($gym);            // (R-122)
        $this->seedSessionThumbnails($gym);           // (R-125)
        $this->seedGymMedia($gym);                                // (R-134, R-135)
        $this->seedMedicalClearances($gym);                       // (R-136)
        $this->seedMinorMember($active->get(8)?->user, $gym);     // (R-129)
        $this->seedSparringProfiles();                            // (R-140)
        $this->seedJobs($gym, $owner);                            // (R-138)
        $this->seedWalkIns($gym, $owner);                         // (R-137)
    }

    /** (R-121) Digits only; saved through the model so the encrypted cast applies. */
    private function seedGovernmentIds(Gym $gym, array $employees): void
    {
        foreach ($employees as $index => $person) {
            if ($index === 2) {
                continue; // contractor: IDs not handed in yet
            }
            $h = (int) sprintf('%u', crc32($person->email));
            $gym->employees()->where('user_id', $person->id)->first()?->update([
                'sss_number' => sprintf('34%08d', $h % 100000000),
                'philhealth_number' => sprintf('19%010d', $h % 10000000000),
                'pagibig_number' => sprintf('1211%08d', intdiv($h, 7) % 100000000),
            ]);
        }
    }

    /** (R-122) Gyms 1 and 2 check location; staff have self clock-ins a few metres from the pin. */
    private function seedClockInLocations(Gym $gym): void
    {
        if ($gym->id > 2) {
            return;
        }
        $gym->forceFill(['attendance_radius_m' => $gym->id === 1 ? 150 : 200])->save();

        $book = app(StaffAttendanceBook::class);
        foreach ($gym->employees()->with('user')->orderBy('id')->get() as $n => $profile) {
            foreach ([3, 2, 1] as $daysAgo) {
                $day = today()->subDays($daysAgo);
                if ($profile->started_on?->gt($day) || ($profile->ended_on && $profile->ended_on->lt($day))) {
                    continue;
                }
                if (app(WorkingDays::class)->kind($gym, $profile->user, $day)->value !== 'working') {
                    continue;
                }
                $shift = $book->scheduleFor($gym, $profile->user, $day);
                if (! $shift['start'] || ! $shift['end'] || $shift['end'] <= $shift['start']) {
                    continue;
                }
                if ($gym->staffAttendance()->where('user_id', $profile->user_id)->whereDate('work_date', $day->toDateString())->exists()) {
                    continue;
                }
                try {
                    PayrollLock::assertOpen($gym, $day);
                } catch (\Throwable) {
                    continue;
                }
                $start = Carbon::parse($day->toDateString().' '.$shift['start']);
                $end = Carbon::parse($day->toDateString().' '.$shift['end']);
                $metres = 15 + ($n * 23 + $daysAgo * 11) % 110;
                Carbon::withTestNow($start->copy()->subMinutes(6), fn () => $book->clockIn($gym, $profile->user, (float) $gym->latitude + $metres / 111320, (float) $gym->longitude));
                Carbon::withTestNow($end->copy()->addMinutes(4), fn () => $book->clockOut($gym, $profile->user));
            }
        }
    }

    /** (R-125) One class service gets a thumbnail on all its sessions. */
    private function seedSessionThumbnails(Gym $gym): void
    {
        $serviceId = TrainingSession::where('type', 'classes')->orderBy('starts_at')->value('gym_service_id');
        if ($serviceId === null) {
            return;
        }
        $path = "training-sessions/{$gym->id}/session-thumbnail.jpg";
        $this->putDemoAsset('public', $path, 'images/session-thumbnail.jpg');
        // Query-builder update: thumbnail_path is not fillable.
        TrainingSession::where('gym_service_id', $serviceId)->update(['thumbnail_path' => $path]);
    }

    /** (R-134, R-135) Cover, six gallery photos and 0-3 policy PDFs. */
    private function seedGymMedia(Gym $gym): void
    {
        $cover = $gym->id === 1 ? 'images/gym-cover.jpg' : sprintf('images/facility-%02d.jpg', (($gym->id - 1) * 2 + 6) % 10 + 1);
        $this->putDemoAsset('public', "gyms/{$gym->id}/cover.jpg", $cover);
        $gym->forceFill(['cover_photo_path' => "gyms/{$gym->id}/cover.jpg"])->save();

        for ($k = 0; $k < 6; $k++) {
            $n = (($gym->id - 1) * 2 + $k) % 10 + 1;
            $path = sprintf("gyms/{$gym->id}/facility-%02d.jpg", $n);
            $size = $this->putDemoAsset('public', $path, sprintf('images/facility-%02d.jpg', $n));
            GymFile::create(['kind' => 'photo', 'path' => $path, 'original_name' => sprintf('facility-%02d.jpg', $n), 'size' => $size, 'sort' => $k + 1]);
        }

        $labels = ['gym-policy' => 'House rules and waiver', 'membership-policy' => 'Membership terms', 'privacy-policy' => 'Data privacy notice'];
        $policies = [1 => ['gym-policy', 'membership-policy', 'privacy-policy'], 2 => ['gym-policy', 'privacy-policy'], 3 => ['membership-policy']][$gym->id] ?? [];
        foreach ($policies as $k => $name) {
            $path = "gyms/{$gym->id}/{$name}.pdf";
            $size = $this->putDemoAsset('public', $path, "documents/{$name}.pdf");
            GymFile::create(['kind' => 'policy', 'path' => $path, 'original_name' => "{$gym->name} - {$labels[$name]}.pdf", 'size' => $size, 'sort' => $k + 1]);
        }
    }

    /** Gym terms apply when the gym has policy text or policy files. */
    private function needsTerms(Gym $gym): bool
    {
        return filled($gym->policies) || GymFile::where('kind', 'policy')->exists();
    }

    /** (R-136) Every membership and athlete profile carries a medical clearance, PDF or scan. */
    private function seedMedicalClearances(Gym $gym): void
    {
        $files = [
            'pdf' => ["medical-files/{$gym->id}/medical-clearance.pdf", 'pdf', 'application/pdf', $this->putDemoAsset('local', "medical-files/{$gym->id}/medical-clearance.pdf", 'documents/medical-certificate.pdf')],
            'png' => ["medical-files/{$gym->id}/medical-clearance.png", 'png', 'image/png', $this->putDemoAsset('local', "medical-files/{$gym->id}/medical-clearance.png", 'images/medical-certificate-scan.png')],
        ];
        $needsTerms = $this->needsTerms($gym);

        foreach (Membership::with('user')->orderBy('id')->get() as $i => $m) {
            [$path, $ext, $mime, $size] = $files[$i % 2 === 0 ? 'pdf' : 'png'];
            $columns = [
                'medical_file_path' => $path,
                'medical_file_name' => 'Medical-Clearance-'.Str::of($m->user->first_name.' '.$m->user->last_name)->replace(' ', '-').'.'.$ext,
                'medical_file_mime' => $mime,
                'medical_file_size' => $size,
            ];
            $fill = $columns;
            if ($needsTerms) {
                $fill['waivers'] = array_values(array_unique([...($m->waivers ?? []), 'gym_terms']));
            }
            $m->forceFill($fill)->save();
            AthleteProfile::where('user_id', $m->user_id)->first()?->forceFill($columns)->save();
        }
    }

    /** (R-129) One member under 18 with guardian details and consent. */
    private function seedMinorMember(?User $member, Gym $gym): void
    {
        if (! $member) {
            return;
        }
        $k = ($gym->id - 1) % 5;
        [$first, $relation] = [['Rosario', 'Mother'], ['Eduardo', 'Father'], ['Marites', 'Mother'], ['Ramil', 'Father'], ['Teresita', 'Mother']][$k];
        $member->forceFill([
            'birthdate' => today()->subYears(14 + $k % 3)->subMonths(3),
            'guardian_name' => "{$first} {$member->last_name}",
            'guardian_phone' => $this->mobile('guardian-'.$member->email),
            'guardian_relationship' => $relation,
            'guardian_consent_at' => $member->created_at->copy()->addMinutes(3),
        ])->save();
    }

    /** (R-140) Mix of known and unknown weight, and empty and real fight records. */
    private function seedSparringProfiles(): void
    {
        $weights = [58.5, 61.0, 63.5, 66.0, 68.5, null, 72.0, 75.5, 52.0, 80.0, 64.0, 70.5, 57.0, 90.0];
        $heights = [165, 168, 170, 172, 175, null, 176, 178, 158, 180, 169, 174, 162, 183];
        $records = [[3, 1, 0], [5, 2, 1], [0, 0, 0], [8, 3, 0], [2, 2, 1], [0, 0, 0], [6, 1, 0], [1, 3, 0], [0, 0, 0], [10, 4, 1], [4, 4, 0], [2, 0, 0], [0, 1, 0], [7, 2, 2]];
        $years = [2, 4, 0, 6, 3, null, 5, 1, 1, 8, 3, 2, 0, 7];

        foreach (AthleteProfile::orderBy('id')->get() as $i => $profile) {
            $j = $i % 14;
            $profile->forceFill([
                'weight_kg' => $weights[$j], 'height_cm' => $heights[$j],
                'wins' => $records[$j][0], 'losses' => $records[$j][1], 'draws' => $records[$j][2],
                'experience_years' => $years[$j],
            ])->save();
        }
    }

    /** (R-138) Three postings and four applications: two waiting, one approved, one rejected. */
    private function seedJobs(Gym $gym, User $owner): void
    {
        $label = config('verification.disciplines')[$gym->disciplines[0] ?? ''] ?? 'Boxing';
        $specs = [
            ['Front desk associate', 'Front Desk', 'open', 30,
                'Welcome members and walk-in visitors, record payments and check-ins, and keep the front desk running during evening classes.',
                'At least six months of customer service or cashier experience. Comfortable with a computer and e-wallet payments. Available on weekday evenings and Saturdays.'],
            ["{$label} assistant coach", 'Coach', 'open', 14,
                'Assist the head coach in beginner and intermediate classes, hold pads, run warm-ups and help keep sparring safe.',
                "At least two years of {$label} training with competition or coaching experience. A basic first aid certificate is preferred."],
            ['Sports massage therapist', 'Sports Therapist', 'closed', 45,
                'Give pre- and post-training massage and recovery sessions, and keep injury notes up to date with the coaches.',
                'Licensed massage therapist or physical therapy graduate. Experience with athletes is an advantage.'],
        ];
        $postings = [];
        foreach ($specs as [$title, $roleName, $status, $daysAgo, $description, $requirements]) {
            $posting = new JobPosting(['title' => $title, 'description' => $description, 'requirements' => $requirements]);
            $posting->forceFill([
                'gym_id' => $gym->id, 'gym_role_id' => GymRole::where('name', $roleName)->value('id'), 'status' => $status,
                'created_by' => $owner->id, 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
            ])->save();
            $postings[] = $posting;
        }

        $names = ['Kevin Lacson', 'Princess Magbanua', 'Ronald Sarmiento', 'Hazel Quinto', 'Jerome Abad', 'Kristine Nepomuceno', 'Ariel Buenaventura', 'Shiela Cortez', 'Ramil Ilagan', 'Joanna Pacheco', 'Bryan Salonga', 'Mylene Dimaculangan', 'Arnel Bustamante', 'Czarina Lim', 'Edgar Macaraeg', 'Faith Sumulong', 'Gilbert Tan', 'Irene Mangahas', 'Wilfredo Ponce', 'Lovely Guevarra'];
        $rows = [
            [0, 'pending', 2, null, "Two years as a cashier and customer service crew at a supermarket in {$gym->city}. Handles cash counts and GCash payments daily.", 'documents/job-credentials.pdf', 'Resume-%s.pdf', 'images/job-valid-id-scan.png', 'UMID-%s.png', null],
            [0, 'rejected', 9, 6, 'Part-time sales associate at a mall sports store for one year.', 'documents/job-credentials.pdf', 'Resume-%s.pdf', 'documents/job-valid-id.pdf', 'Drivers-License-%s.pdf', 'We need someone who can cover the 6:00 AM opening shift on weekdays. Please apply again if your schedule changes.'],
            [1, 'pending', 1, null, "Five years of amateur {$label} with twelve interclub bouts. Helps run kids' classes at a barangay sports program on weekends.", 'images/job-credentials-scan.png', 'Coaching-Certificate-%s.png', 'images/job-valid-id-scan.png', 'PhilSys-ID-%s.png', null],
            [2, 'approved', 20, 14, 'Licensed massage therapist with three years at a sports clinic in Makati. Worked with a university varsity team during the UAAP season.', 'documents/job-credentials.pdf', 'PRC-License-and-Resume-%s.pdf', 'documents/job-valid-id.pdf', 'Drivers-License-%s.pdf', null],
        ];

        foreach ($rows as $r => [$p, $status, $createdAgo, $decidedAgo, $experience, $credAsset, $credName, $idAsset, $idName, $reason]) {
            [$first, $last] = explode(' ', $names[($gym->id - 1) * 4 + $r], 2);
            $user = $this->user($first, $last, Str::slug("$first $last", '.').'@demo.combatplatform.ph', $this->mobile('job-'.$first.$last));
            $posting = $postings[$p];
            $slug = "{$first}-{$last}";
            $files = [];
            foreach (['credentials' => [$credAsset, $credName], 'valid_id' => [$idAsset, $idName]] as $kind => [$asset, $name]) {
                $path = "job-applications/{$gym->id}/{$user->id}-{$kind}.".pathinfo($asset, PATHINFO_EXTENSION);
                $this->putDemoAsset('local', $path, $asset);
                $files["{$kind}_path"] = $path;
                $files["{$kind}_name"] = sprintf($name, $slug);
            }

            $application = new JobApplication(['experience' => $experience]);
            $application->forceFill([
                'gym_id' => $gym->id, 'job_posting_id' => $posting->id, 'user_id' => $user->id, 'status' => $status, ...$files,
                'decided_by' => $decidedAgo === null ? null : $owner->id,
                'decided_at' => $decidedAgo === null ? null : now()->subDays($decidedAgo),
                'decision_reason' => $reason,
                'created_at' => now()->subDays($createdAgo), 'updated_at' => now()->subDays($decidedAgo ?? $createdAgo),
            ])->save();

            if ($status === 'pending') {
                $gym->peopleWithPermission('hr.job_applications.approve')->each(fn (User $person) => $person->notify(new GymNotice('New job application', "{$user->fullName()} applied for {$posting->title}.", $gym->id, "/gyms/{$gym->id}/manage/hiring")));
            } elseif ($status === 'approved') {
                $this->hireMember($gym, $user, ['job_title' => $posting->title], $posting->role, today()->subDays(14));
                $user->notify(new GymNotice('Job application approved', "You are now {$posting->title} at {$gym->name}. Open My work to see your details.", $gym->id, "/gyms/{$gym->id}/manage/my-work"));
            } else {
                $user->notify(new GymNotice('Job application not approved', "{$gym->name} did not approve your application for {$posting->title}. Reason: {$reason}", $gym->id, "/gyms/{$gym->id}"));
            }
        }
    }

    /** Platform-wide pass, once at the end of the seed. */
    private function seedP10P11Platform(): void
    {
        $this->seedIdentities();       // (R-128, R-130)
        $this->seedDocumentReview();   // (R-132, R-133)
    }

    /** (R-128, R-130) Birthdate for everyone; middle name, suffix, city and province for most. */
    private function seedIdentities(): void
    {
        $middles = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Castro', 'Aquino', 'Lopez', 'Navarro', 'Ramos', 'Gonzales', 'Mercado', 'Javier'];
        $suffixes = ['Nestor Cabral' => 'Jr.', 'Paolo Reyes' => 'III', 'Daniel Villamor' => 'Jr.'];
        $cities = ['Dasmariñas', 'Imus', 'Bacoor', 'General Trias', 'Silang', 'Tagaytay'];

        foreach (User::orderBy('id')->get() as $user) {
            $h = (int) sprintf('%u', crc32($user->email));
            $middle = $h % 4 === 0 ? null : $middles[$h % 16];
            $city = null;
            if ($h % 5 !== 0) {
                $city = DB::table('gym_user')->join('gyms', 'gyms.id', '=', 'gym_user.gym_id')
                    ->where('gym_user.user_id', $user->id)->orderBy('gym_user.id')->value('gyms.city') ?? $cities[$h % 6];
            }
            $user->forceFill([
                'birthdate' => $user->birthdate ?? today()->subYears($user->account_type === AccountType::Owner ? 32 + $h % 20 : 19 + $h % 24)->subDays($h % 300),
                'middle_name' => $middle === $user->last_name ? null : $middle,
                'suffix' => $suffixes["{$user->first_name} {$user->last_name}"] ?? null,
                'city' => $city,
                'province' => $city ? 'Cavite' : null,
            ])->save();
        }
    }

    /** (R-132, R-133) Which documents the admin has opened, plus expiry and name-check flags that match each story. */
    private function seedDocumentReview(): void
    {
        foreach (Gym::with('documents')->orderBy('id')->get() as $gym) {
            if ($gym->documents->isEmpty() || $gym->status === GymStatus::Draft) {
                continue; // a draft has not reached the admin yet
            }
            $mustOpen = fn ($d) => config("verification.documents.{$d->type}.must_open", true);
            $base = $gym->reviewed_at ?? now()->subDay();
            foreach ($gym->documents->filter($mustOpen)->values() as $i => $doc) {
                if ($gym->status === GymStatus::Submitted) {
                    $viewed = $doc->type === 'dti' ? now()->subHours(3) : null;
                } else {
                    $viewed = $base->copy()->subMinutes(60 - $i * 4);
                }
                $doc->forceFill(['admin_viewed_at' => $viewed])->save();
            }
            if ($gym->status === GymStatus::Returned) {
                $gym->documents->firstWhere('type', 'mayors_permit')?->forceFill(['expires_on' => today()->subMonthsNoOverflow(2)->endOfMonth()])->save();
            } elseif ($gym->status === GymStatus::Rejected) {
                $gym->documents->firstWhere('type', 'dti')?->forceFill(['ocr_flags' => ['gym_name']])->save();
            } elseif ($gym->status === GymStatus::UnderReview) {
                $gym->documents->firstWhere('type', 'owner_id')?->forceFill(['ocr_flags' => ['owner_name']])->save();
            }
        }
    }

    /** (R-137) Five kiosk visits: three today (one paid), one yesterday and one three days ago (both paid). */
    private function seedWalkIns(Gym $gym, User $owner): void
    {
        $desk = GymRole::where('name', 'Front Desk')->first()?->users()->first() ?? $owner;
        $names = ['Jericho Manalastas', 'Abegail Ferrer', 'Ronnie Villareal', 'Precious Lagman', 'Dominic Yap', 'Kimberly Sison', 'Arvin Talusan', 'Hannah Robles', 'Paulo Galang', 'Trixie Mangubat'];
        $rows = [
            [now()->subMinutes(25)->max(today()), true, false, null],
            [now()->subMinutes(70)->max(today()), false, true, null],
            [now()->subMinutes(130)->max(today()), true, false, 'cash'],
            [today()->subDay()->setTime(18, 30), false, false, 'ewallet'],
            [today()->subDays(3)->setTime(17, 45), false, false, 'card'],
        ];
        $terms = $this->needsTerms($gym) ? ['gym_terms'] : [];

        foreach ($rows as $k => [$at, $withEmail, $injured, $method]) {
            $name = $names[(($gym->id - 1) * 2 + $k) % 10];
            $email = $withEmail ? Str::slug($name, '.').'@demo.combatplatform.ph' : null;
            $phone = $this->mobile('walk-in-'.$name);

            $customer = Customer::create(['name' => $name, 'email' => $email, 'phone' => $phone, 'source' => CustomerSource::WalkIn, 'inquiry' => 'Walk-in visit']);
            $customer->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

            $row = WalkIn::create([
                'customer_id' => $customer->id, 'name' => $name, 'phone' => $phone, 'email' => $email,
                'health_answers' => array_map(fn () => false, array_flip(array_keys(config('membership.health_questions')))),
                'health_notes' => $injured ? 'Sprained left wrist three months ago; cleared by a doctor but trains with a brace.' : null,
                'waivers' => [...array_keys(config('membership.waivers')), ...$terms],
                'signed_name' => $name, 'checked_in_at' => $at,
            ]);
            if ($injured) {
                $row->health_answers = [...$row->health_answers, 'recent_injury' => true];
            }
            $row->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

            if ($method) {
                $payment = Carbon::withTestNow($at->copy()->addMinutes(4), fn () => app(SaleRecorder::class)->record($gym, $desk, [
                    'income_type' => 'walk_in', 'method' => $method, 'amount' => 350 + $gym->id * 25,
                    'payer_name' => $name, 'note' => "Walk-in visit #{$row->id}",
                ]));
                $row->forceFill(['payment_id' => $payment->id])->save();
            }
        }
    }
}
