<?php

namespace Database\Seeders;

use App\Models\CoachApplication;
use App\Models\EquipmentCheck;
use App\Models\GoalMilestone;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Injury;
use App\Models\InjuryUpdate;
use App\Models\MembershipPlan;
use App\Models\Offer;
use App\Models\StockItem;
use App\Models\TrainingGoal;
use App\Models\User;
use App\Notifications\GymNotice;
use App\Support\Procurement\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** P09 demo records: items waiting for a decision, goals, injuries, equipment checks, receipt proof. */
trait P09Scenarios
{
    /** One call per approved gym, after members, stock, procurement and HR are seeded. */
    private function seedP09(Gym $gym, User $owner, array $employees): void
    {
        $desk = GymRole::where('name', 'Front Desk')->first()?->users()->first() ?? $owner;
        $coach = $gym->employees()->where('coach_public', true)->first()?->user ?? $owner;
        $active = $gym->memberships()->with('user')->where('status', 'active')->orderBy('id')->get();

        $this->seedOffers($gym, $owner, $desk);
        $this->seedCoachApplications($gym, $owner, $active);
        $this->seedPurchasingP09($gym, $owner, $employees);
        $this->seedGoals($gym, $coach, $active);
        $this->seedInjuries($gym, $owner, $active);
        $this->seedEquipmentChecks($gym, $owner);
        $this->quietMembers($gym, $active);
    }

    private function seedOffers(Gym $gym, User $owner, User $desk): void
    {
        $plan = MembershipPlan::where('billing_type', 'time')->orderBy('id')->first();
        $rows = [
            ['promo', 'Rainy season promo', null, 'percent', 10, -10, 20, null, 3, 'approved', $owner, $owner, null],
            ['voucher', 'Welcome voucher', 'WELCOME10', 'percent', 10, 0, 60, 20, 0, 'pending', $desk, null, null],
            ['voucher', 'Friend referral voucher', 'FRIEND200', 'fixed', 200, -30, 30, 5, 5, 'approved', $desk, $owner, null],
            ['promo', 'Back to school promo', null, 'percent', 25, 5, 40, null, 0, 'rejected', $desk, $owner, 'A 25% discount is too deep for a monthly plan. Resubmit at 10% or less.'],
            ['promo', 'Summer sale', null, 'fixed', 300, -60, -20, null, 11, 'approved', $owner, $owner, null],
        ];

        foreach ($rows as [$kind, $name, $code, $type, $value, $startDays, $endDays, $max, $uses, $status, $maker, $decider, $reason]) {
            $offer = new Offer([
                'kind' => $kind, 'name' => $name, 'code' => $code, 'discount_type' => $type, 'value' => $value,
                'starts_on' => today()->addDays($startDays), 'ends_on' => today()->addDays($endDays), 'max_uses' => $max,
            ]);
            $offer->forceFill([
                'gym_id' => $gym->id, 'uses' => $uses, 'status' => $status, 'created_by' => $maker->id,
                'decided_by' => $decider?->id, 'decided_at' => $decider ? now()->subDays(2) : null, 'decision_reason' => $reason,
                'created_at' => now()->subDays(4), 'updated_at' => now()->subDays(2),
            ])->save();
            $offer->plans()->attach($plan->id);

            if ($status === 'pending') {
                $owner->notify(new GymNotice('Offer waiting for approval', "{$name} was made by ".trim("{$maker->first_name} {$maker->last_name}").'.', $gym->id, "/gyms/{$gym->id}/manage/offers"));
            }
        }
    }

    private function seedCoachApplications(Gym $gym, User $owner, $active): void
    {
        $applicants = $active->pluck('user')->unique('id')->values();
        $rows = [
            ['pending', 'Competed for six years and coached youth classes at a school club.', 'Assistant coach', 'Boxing fundamentals', [['tue', '17:00', '20:00'], ['thu', '17:00', '20:00']], null],
            ['pending', 'Former interclub fighter. Runs conditioning sessions for a running club.', 'Conditioning coach', 'Strength and conditioning', [['sat', '08:00', '12:00']], null],
            ['rejected', 'Two years of recreational training. Wants to help with beginner classes.', 'Novice', 'Beginner classes', [['mon', '18:00', '20:00']], 'Please gain more teaching experience and apply again after a coaching course.'],
            ['withdrawn', 'Certified fitness instructor, new to combat sports.', 'Fitness instructor', 'Mobility', [['wed', '09:00', '11:00']], null],
        ];

        foreach ($rows as $index => [$status, $experience, $rank, $specialisation, $slots, $reason]) {
            $user = $applicants[$index + 3] ?? null;
            if (! $user) {
                continue;
            }
            $application = new CoachApplication([
                'experience' => $experience, 'rank' => $rank, 'specialisation' => $specialisation,
                'availability' => array_map(fn ($slot) => ['weekday' => $slot[0], 'start' => $slot[1], 'end' => $slot[2]], $slots),
            ]);
            $application->forceFill([
                'gym_id' => $gym->id, 'user_id' => $user->id, 'status' => $status,
                'decided_by' => $status === 'rejected' ? $owner->id : null, 'decided_at' => $status === 'rejected' ? now()->subDay() : null,
                'decision_reason' => $reason, 'created_at' => now()->subDays(3 + $index), 'updated_at' => now()->subDays(3 + $index),
            ])->save();

            if ($status === 'pending') {
                $owner->notify(new GymNotice('Coach application waiting', trim("{$user->first_name} {$user->last_name}").' applied to coach.', $gym->id, "/gyms/{$gym->id}/manage/coach-applications"));
            }
        }
    }

    /** A second request waiting for approval, and a delivery photo on the part-received order. */
    private function seedPurchasingP09(Gym $gym, User $owner, array $employees): void
    {
        $items = $gym->stockItems()->orderBy('id')->get();
        $requester = collect($employees)->first(fn (User $person) => $person->id !== $owner->id) ?? $owner;

        $request = Carbon::withTestNow(now()->subDay()->setTime(15, 0), function () use ($gym, $requester, $items) {
            $request = $gym->purchaseRequests()->create([
                'number' => 'TMP-'.Str::upper(Str::random(16)), 'status' => 'pending', 'requested_by' => $requester->id,
                'needed_by' => today()->addDays(10)->toDateString(), 'note' => 'Replace worn gear before the next sparring night.',
            ]);
            $request->forceFill(['number' => sprintf('PR-%d-%06d', $gym->id, $request->id)])->save();
            foreach ([[$items[0], 6, '1850.00'], [$items[3], 2, '1450.00']] as [$item, $quantity, $cost]) {
                $request->lines()->create(['stock_item_id' => $item->id, 'quantity' => $quantity, 'est_unit_cost' => $cost]);
            }

            return $request->load('lines');
        });
        app(Notifier::class)->requestSubmitted($gym, $request, $requester);

        $order = $gym->purchaseOrders()->where('status', 'partly_received')->first();
        if ($order) {
            // 1x1 PNG stands in for the delivery photo.
            $path = "purchase-proofs/{$gym->id}/delivery-note-{$order->id}.png";
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
            Storage::disk('local')->put($path, $png);
            DB::table('purchase_order_proofs')->insert([
                'gym_id' => $gym->id, 'purchase_order_id' => $order->id,
                'goods_receipt_id' => DB::table('goods_receipts')->where('purchase_order_id', $order->id)->value('id'),
                'path' => $path, 'original_name' => 'delivery-note.png', 'mime' => 'image/png', 'size' => strlen($png),
                'uploaded_by' => $owner->id, 'created_at' => now()->subDays(6), 'updated_at' => now()->subDays(6),
            ]);
        }
    }

    private function seedGoals(Gym $gym, User $coach, $active): void
    {
        $specs = [
            ['Win a first interclub round', 'Build ring confidence for the next interclub event.', 40, 'active', [['Spar three rounds without stopping', 20, true], ['Sparring session with a new partner', 25, true], ['Fight-simulation session', 35, false]]],
            ['Complete a 12-week conditioning block', 'Improve stamina and footwork.', -5, 'achieved', [['Weeks 1 to 4', -40, true], ['Weeks 5 to 8', -25, true], ['Weeks 9 to 12', -6, true]]],
            ['Lose 4 kg before the annual weigh-in', 'Steady loss through training and nutrition.', -3, 'active', [['Reach minus 2 kg', -15, true], ['Reach minus 4 kg', -3, false]]],
        ];

        foreach ($specs as $index => [$title, $description, $targetDays, $status, $milestones]) {
            $member = $active[$index + 1] ?? null;
            if (! $member) {
                continue;
            }
            $goal = new TrainingGoal(['title' => $title, 'description' => $description, 'target_on' => today()->addDays($targetDays)]);
            $goal->forceFill([
                'gym_id' => $gym->id, 'user_id' => $member->user_id, 'status' => $status, 'created_by' => $coach->id,
                'achieved_on' => $status === 'achieved' ? today()->subDays(6) : null,
                'created_at' => now()->subDays(45), 'updated_at' => now()->subDays(6),
            ])->save();

            foreach ($milestones as $position => [$text, $dueDays, $done]) {
                $row = new GoalMilestone(['title' => $text, 'due_on' => today()->addDays($dueDays), 'position' => $position]);
                $row->forceFill([
                    'gym_id' => $gym->id, 'training_goal_id' => $goal->id,
                    'done_on' => $done ? today()->addDays(min($dueDays, -1)) : null, 'done_by' => $done ? $member->user_id : null,
                ])->save();
            }
        }
    }

    private function seedInjuries(Gym $gym, User $owner, $active): void
    {
        $therapist = GymRole::where('name', 'Sports Therapist')->first()?->users()->first() ?? $owner;
        $specs = [
            [2, 'Left ankle', 'Sprain after a jumping drill.', 'moderate', 'active', 'No jumping, skipping or contact drills until cleared.', 5, [['active', 'Reported after class. Ice and rest advised.', 5]]],
            [4, 'Right wrist', 'Strain from heavy bag work.', 'minor', 'recovering', 'No heavy bag work. Pad work with light gloves only.', 18, [['active', 'Swelling after training.', 18], ['recovering', 'Swelling down; light pad work allowed.', 8]]],
            [5, 'Shoulder', 'Dislocation during grappling; reduced and rested.', 'severe', 'cleared', null, 60, [['active', 'Seen by a doctor; sling for two weeks.', 60], ['recovering', 'Physio started.', 35], ['cleared', 'Full range of motion. Doctor note received.', 10]]],
        ];

        foreach ($specs as [$index, $part, $description, $severity, $status, $restrictions, $daysAgo, $log]) {
            $member = $active[$index] ?? null;
            if (! $member) {
                continue;
            }
            $injury = new Injury(['body_part' => $part, 'description' => $description, 'severity' => $severity, 'occurred_on' => today()->subDays($daysAgo), 'restrictions' => $restrictions]);
            $injury->forceFill([
                'gym_id' => $gym->id, 'user_id' => $member->user_id, 'status' => $status,
                'cleared_on' => $status === 'cleared' ? today()->subDays(10) : null,
                'reported_by' => $index === 2 ? $member->user_id : $therapist->id,
                'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays(end($log)[2]),
            ])->save();

            foreach ($log as [$logStatus, $note, $logDaysAgo]) {
                $update = new InjuryUpdate;
                $update->forceFill([
                    'gym_id' => $gym->id, 'injury_id' => $injury->id, 'status' => $logStatus, 'note' => $note,
                    'recorded_by' => $therapist->id, 'created_at' => now()->subDays($logDaysAgo), 'updated_at' => now()->subDays($logDaysAgo),
                ])->save();
            }
        }
    }

    private function seedEquipmentChecks(Gym $gym, User $owner): void
    {
        $training = StockItem::where('sku', 'G'.$gym->id.'-TRAIN')->first();
        $specs = [
            ['Boxing gloves 14 oz', 'ok', 30, -3, 'Stitching and padding checked.', 33],
            ['Focus mitts', 'needs_repair', 60, 10, 'Torn padding on one mitt. Send for repair.', 4],
            ['Mouth guards', 'ok', 14, 3, 'No cracks. Sanitised.', 11],
            [$training?->name, 'unsafe', 30, 25, 'Cracked foam and loose strap. Remove from use.', 5],
        ];

        foreach ($specs as [$name, $result, $interval, $nextDays, $note, $daysAgo]) {
            $item = $name ? StockItem::where('gym_id', $gym->id)->where('name', $name)->first() : null;
            if (! $item) {
                continue;
            }
            $check = new EquipmentCheck;
            $check->forceFill([
                'gym_id' => $gym->id, 'stock_item_id' => $item->id, 'result' => $result, 'note' => $note,
                'checked_by' => $owner->id, 'checked_on' => today()->subDays($daysAgo),
                'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
            ])->save();
            $item->forceFill(['safety_status' => $result, 'check_interval_days' => $interval, 'next_check_on' => today()->addDays($nextDays)])->save();
        }
    }

    /** The last two active members stop coming, so the daily inactivity job has something to report. */
    private function quietMembers(Gym $gym, $active): void
    {
        $cutoff = today()->subDays(((int) ($gym->inactivity_days ?: 14)) + 1);
        foreach ($active->slice(-2) as $member) {
            DB::table('gym_visits')->where('gym_id', $gym->id)->where('user_id', $member->user_id)->where('checked_in_at', '>=', $cutoff)->delete();
            DB::table('session_bookings')->where('gym_id', $gym->id)->where('user_id', $member->user_id)
                ->whereIn('attendance', ['present', 'late'])->update(['attendance' => 'absent']);
        }
    }
}
