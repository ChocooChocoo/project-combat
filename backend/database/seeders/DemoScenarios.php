<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\SessionBooking;
use App\Models\StockItem;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\AttendanceLedger;
use App\Support\BookingLedger;
use App\Support\Procurement\Ordering;
use App\Support\Procurement\Payables;
use App\Support\Procurement\Receiving;
use App\Support\SessionScheduler;
use App\Support\StaffAttendanceBook;
use App\Support\StockLedger;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Additional synthetic records for exercising real workflows without external services. */
trait DemoScenarios
{
    private function contactEmail(string $email): string
    {
        if (str_ends_with($email, '@gmail.com') || str_ends_with($email, '.test')) {
            return Str::before($email, '@').'@demo.combatplatform.ph';
        }

        return $email;
    }

    private function phone(string $given): string
    {
        return str_contains($given, '555') ? $this->mobile($given) : $given;
    }

    private function mobile(string $key): string
    {
        $prefixes = ['0917', '0918', '0920', '0921', '0927', '0932', '0936', '0945', '0956', '0961', '0975', '0995'];
        $number = (int) sprintf('%u', crc32($key));

        return $prefixes[$number % count($prefixes)].' '.substr((string) (1000000 + $number % 9000000), 0, 3).' '.substr((string) (1000000 + $number % 9000000), 3);
    }

    private function personName(int $gymId, int $index): string
    {
        $first = ['Elena', 'Rafael', 'Isabel', 'Marco', 'Diana', 'Adrian', 'Beatriz', 'Nico', 'Clara', 'Enzo', 'Mika', 'Leo', 'Julia', 'Tomas', 'Rico', 'Mara', 'Celina', 'Francis', 'Rochelle', 'Jose', 'Teresa', 'Vincent', 'Marielle', 'Dennis', 'Carmela', 'Joshua', 'Danica', 'Lorenzo', 'Rowena', 'Angelo', 'Luisa'];
        $last = ['Dela Cruz', 'Santos', 'Garcia', 'Torres', 'Flores', 'Castillo', 'Ramos', 'Rivera', 'Domingo', 'Aguilar', 'Salcedo', 'Valdez', 'Pascual', 'Estrada', 'Dizon', 'Velasco', 'Fontanilla', 'Ocampo', 'Villaflor', 'Soriano', 'Malabanan', 'Manalo', 'Alcantara', 'Pineda', 'De Leon', 'Rosales', 'Vergara', 'Evangelista', 'Bautista', 'Tolentino', 'David'];

        return $first[($index + ($gymId - 1) * 7) % count($first)].' '.$last[($index + ($gymId - 1) * 11) % count($last)];
    }

    private function personEmail(int $gymId, int $index): string
    {
        return Str::slug($this->personName($gymId, $index), '.').'@demo.combatplatform.ph';
    }

    private function memberProfile(Gym $gym, User $member, int $index): array
    {
        $backgrounds = [
            'Office worker looking for a regular evening training routine.',
            'University student who trained with a school sports club.',
            'Returning to training after a year focused on work and family.',
            'Recreational runner adding strength and coordination work.',
            'Shift worker who prefers morning sessions on days off.',
            'Former school athlete preparing for a first interclub event.',
            'Parent joining weekend classes alongside a teenage child.',
        ];
        $history = [
            'First structured combat sports classes; has attended two trial sessions.',
            'Six months of group training, usually twice a week.',
            'Previously trained for two years; rebuilding fitness before sparring.',
            'Attends weekend classes and practises footwork at home.',
            'Completed an introductory course at a community sports centre.',
            'Three years of recreational training with occasional interclub rounds.',
            'New to this discipline; previous experience in general fitness classes.',
        ];
        $answers = array_fill_keys(array_keys(config('membership.health_questions')), false);
        if ($index % 5 === 2) {
            $answers['recent_injury'] = true;
        }

        return [
            'emergency_contacts' => [[
                'name' => $this->personName($gym->id, $index + 40),
                'relationship' => ['Sibling', 'Parent', 'Spouse', 'Friend'][$index % 4],
                'phone' => $this->mobile('emergency-'.$member->email),
            ]],
            'background' => $backgrounds[$index % count($backgrounds)],
            'training_history' => $history[$index % count($history)],
            'current_rank' => ['Beginner', 'Novice', 'Intermediate', 'Recreational athlete'][$index % 4],
            'health_answers' => $answers,
            'health_notes' => $answers['recent_injury']
                ? 'Recovering from an ankle sprain. No jumping or contact drills until reviewed by the therapist.'
                : ['No current injuries or training restrictions reported.', 'Reports occasional muscle soreness after long shifts. Requests a gradual warm-up.', 'No regular medication reported. Prefers lower-impact conditioning when returning from a break.'][$index % 3],
        ];
    }

    private function currentLeaves(array $leaves): array
    {
        $first = $leaves[0];
        $leaves[] = [...$first, 'status' => 'cancelled', 'reason' => 'Travel plans changed; no longer needs these days off.'];
        if (! collect($leaves)->contains('status', 'rejected')) {
            $leaves[] = [...$first, 'status' => 'rejected', 'reason' => 'Requested leave during the interclub event.', 'decision_note' => 'Please choose another date; two coaches are already away for the event.'];
        }
        foreach ($leaves as $index => &$leave) {
            $days = max(1, (int) Carbon::parse($leave['starts_on'])->diffInDays(Carbon::parse($leave['ends_on'])) + 1);
            $start = $leave['status'] === 'pending'
                ? today()->addWeeks(2)->addDays($index * 4)
                : today()->subDays(35 + $index * 7);
            $leave['starts_on'] = $start->toDateString();
            $leave['ends_on'] = $start->copy()->addDays($days - 1)->toDateString();
        }
        unset($leave);

        return $leaves;
    }

    private function deskSale(Gym $gym, User $owner, string $type, float $amount, string $name, string $note, string $method = 'cash'): Payment
    {
        $payment = $gym->payments()->create([
            'income_type' => $type, 'amount' => $amount, 'method' => $method,
            'payer_name' => $name, 'note' => $note, 'received_by' => $owner->id, 'paid_at' => now(),
        ]);
        $payment->update(['receipt_number' => sprintf('OR-%d-%06d', $gym->id, $payment->id)]);

        return $payment;
    }

    private function seedStockScenarios(Gym $gym, User $owner, array $items): void
    {
        $ledger = app(StockLedger::class);
        Carbon::withTestNow(now()->subDay()->setTime(11, 0), function () use ($gym, $owner, $ledger, $items) {
            $ledger->move($items[4], 'lost', 1, $owner, ['note' => 'Rope missing after the interclub event; checked equipment cage and lost property.']);
            $payment = $this->deskSale($gym, $owner, 'merchandise', (float) $items[7]->sale_price, $this->personName($gym->id, 4), 'One gym shirt, size medium.', 'ewallet');
            $ledger->move($items[7], 'sale', 1, $owner, ['payment_id' => $payment->id, 'note' => 'Front desk shirt sale.']);
        });
        $retired = $ledger->open($gym, ['name' => 'Retired timer remote', 'sku' => 'G'.$gym->id.'-REMOTE', 'category' => 'equipment', 'unit' => 'piece', 'location' => 'Maintenance cabinet', 'unit_cost' => '450.00'], 0, $owner);
        $retired->update(['status' => 'archived']);
        $ledger->open($gym, ['name' => 'Instant cold packs', 'sku' => 'G'.$gym->id.'-ICE', 'category' => 'supplies', 'unit' => 'piece', 'location' => 'First aid cabinet', 'min_level' => 5, 'reorder_qty' => 20, 'unit_cost' => '45.00'], 0, $owner);
        $disciplineItem = match ($gym->disciplines[0]) {
            'bjj' => ['Training gi, adult A2', 'piece', 2450],
            'arnis' => ['Rattan arnis sticks', 'pair', 380],
            'mma' => ['Grappling gloves, medium', 'pair', 1650],
            default => ['Boxing headguard, medium', 'piece', 2100],
        };
        [$name, $unit, $cost] = $disciplineItem;
        $ledger->open($gym, ['name' => $name, 'sku' => 'G'.$gym->id.'-TRAIN', 'category' => 'protective_gear', 'unit' => $unit, 'location' => 'Equipment cage', 'min_level' => 2, 'unit_cost' => $cost], 8 + $gym->id, $owner);
    }

    private function seedFinanceScenarios(Gym $gym, User $owner): void
    {
        Carbon::withTestNow(now()->subDays(3)->setTime(14, 15), function () use ($gym, $owner) {
            $this->deskSale($gym, $owner, 'walk_in', 350 + $gym->id * 25, $this->personName($gym->id, 23), 'Single-visit training access.', 'card');
            $this->deskSale($gym, $owner, 'other', 150, $this->personName($gym->id, 24), 'Monthly locker rental.');
            foreach ([
                ['equipment', 2400, 'Replacement wall timer and mounting bracket.'],
                ['maintenance', 1800, 'Replaced worn mat edging and secured the loose corner.'],
                ['professional_fees', 2500, 'Bookkeeping and monthly payroll reconciliation.'],
                ['taxes_licenses', 1250, 'Business permit processing fee.'],
                ['other', 380, 'Courier fee for returned supplier samples.'],
            ] as [$category, $amount, $note]) {
                $gym->expenses()->create([
                    'category' => $category, 'amount' => $amount + $gym->id * 35,
                    'incurred_on' => today()->toDateString(), 'method' => 'cash', 'note' => $note, 'recorded_by' => $owner->id,
                ]);
            }
        });
    }

    private function seedWorkshop(Gym $gym, User $owner): void
    {
        $coach = $gym->employees()->where('coach_public', true)->firstOrFail();
        $member = $gym->memberships()->where('status', 'active')->firstOrFail();
        $scheduler = app(SessionScheduler::class);
        for ($offset = 20; $offset >= 2; $offset--) {
            $day = today()->subDays($offset);
            $shift = app(StaffAttendanceBook::class)->scheduleFor($gym, $coach->user, $day);
            if (! $shift['start'] || ! ($hours = $gym->operating_hours[strtolower($day->format('D'))] ?? null)) {
                continue;
            }
            $start = Carbon::parse($day->toDateString().' '.max($shift['start'], $hours['open']));
            $end = $start->copy()->addHour();
            if ($end->format('H:i') > min($shift['end'], $hours['close'])) {
                continue;
            }
            foreach ($gym->rooms()->get() as $room) {
                if ($scheduler->problems($coach->user_id, $room->id, $start, $end) !== []) {
                    continue;
                }
                Carbon::withTestNow($start->copy()->subDays(3), function () use ($gym, $owner, $coach, $member, $start, $end, $room) {
                    $service = $gym->gymServices()->create([
                        'name' => 'Technical workshop', 'discipline' => $gym->disciplines[0], 'activity_type' => 'classes',
                        'overview' => 'Small-group technique review with individual feedback. Separately charged workshop outside regular membership classes.',
                        'level' => 'all', 'duration_minutes' => 60, 'frequency' => 'Special workshop by reservation',
                        'included' => ['Individual technique review', 'Coach feedback'], 'bring' => ['Training gear', 'Water bottle'],
                        'price' => 650, 'price_included' => false, 'published_at' => now(),
                    ]);
                    $session = TrainingSession::create([
                        'gym_service_id' => $service->id, 'type' => 'classes', 'name' => $service->name,
                        'discipline' => $service->discipline, 'level' => 'all', 'coach_id' => $coach->user_id, 'room_id' => $room->id,
                        'starts_at' => $start, 'ends_at' => $end, 'capacity' => 6, 'fee' => 650, 'needs_approval' => false,
                        'status' => 'scheduled', 'content' => $service->overview, 'created_by' => $owner->id,
                    ]);
                    $booking = SessionBooking::create([
                        'training_session_id' => $session->id, 'user_id' => $member->user_id, 'membership_id' => $member->id,
                        'status' => 'booked', 'fee' => 650,
                    ]);
                    // Synthetic gateway references never go to PayMongo; seeding must work offline.
                    app(BookingLedger::class)->recordPayment($booking, 650, PaymentMethod::Paymongo, null, [
                        'paymongo_checkout_id' => 'cs_'.substr(hash('sha256', 'workshop-'.$gym->id), 0, 24),
                        'paymongo_payment_id' => 'pay_'.substr(hash('sha256', 'workshop-'.$gym->id), 0, 24),
                    ]);
                    Carbon::withTestNow($start, fn () => app(AttendanceLedger::class)->mark($booking, 'present', $owner, 'account', $start));
                    $booking->forceFill(['check_out_at' => $end->copy()->addMinutes(5)])->save();
                });

                return;
            }
        }
        throw new \LogicException('No conflict-free technical workshop slot for '.$gym->name);
    }

    private function seedProcurementScenarios(Gym $gym, User $owner, User $requester, Supplier $supplier, StockItem $primary, StockItem $secondary): void
    {
        $gym->suppliers()->create([
            'name' => $gym->city.' Athletics Depot', 'contact_person' => $this->personName($gym->id, 28),
            'phone' => $this->mobile('supplier-old-'.$gym->id), 'email' => 'athletics.'.Str::slug($gym->city).'@demo.combatplatform.ph',
            'address' => '29 Bonifacio Street, '.$gym->city, 'payment_terms' => 'Cash on delivery',
            'status' => 'archived', 'notes' => 'Stopped trading locally. Keep the supplier record for earlier purchases.',
        ]);

        foreach (['pending', 'rejected', 'cancelled'] as $index => $status) {
            Carbon::withTestNow(now()->subDays(9 - $index)->setTime(10, 30), function () use ($gym, $owner, $requester, $primary, $status) {
                $need = $gym->supplyNeeds()->create([
                    'stock_item_id' => $primary->id, 'needed_by' => today()->addDays(14)->toDateString(), 'quantity' => 6,
                    'status' => $status === 'pending' ? 'requested' : 'open', 'created_by' => $requester->id,
                    'note' => match ($status) {
                        'pending' => 'Reserve stock for the next beginner intake.',
                        'rejected' => 'Spare equipment for the travelling team.',
                        default => 'Extra stock for the postponed weekend clinic.',
                    },
                ]);
                $request = $gym->purchaseRequests()->create([
                    'number' => 'TMP-'.Str::random(16), 'status' => $status, 'requested_by' => $requester->id,
                    'needed_by' => $need->needed_by, 'note' => $need->note,
                    'decided_by' => $status === 'pending' ? null : $owner->id,
                    'decided_at' => $status === 'pending' ? null : now()->addHour(),
                    'decision_reason' => match ($status) {
                        'rejected' => 'Existing reserve stock is sufficient; verify the equipment count before reordering.',
                        'cancelled' => 'Clinic postponed. Reassess quantities when the new date is confirmed.',
                        default => null,
                    },
                ]);
                $request->update(['number' => sprintf('PR-%d-%06d', $gym->id, $request->id)]);
                $request->lines()->create(['stock_item_id' => $primary->id, 'quantity' => 6, 'est_unit_cost' => $primary->unit_cost, 'supply_need_id' => $need->id]);
            });
        }

        foreach (['pending', 'completed_paid', 'completed_unpaid', 'cancelled', 'closed_short'] as $index => $scenario) {
            Carbon::withTestNow(now()->subDays(5)->setTime(9 + $index, 0), function () use ($gym, $owner, $supplier, $secondary, $scenario) {
                $ordering = app(Ordering::class);
                $order = $ordering->direct($gym, $supplier, [
                    ['stock_item_id' => $secondary->id, 'quantity' => 4, 'unit_cost' => $secondary->unit_cost],
                ], $owner, today()->addDays(7));
                if ($scenario === 'cancelled') {
                    $ordering->cancel($order, 'Supplier cannot meet the delivery date; source an alternative locally.', $owner);
                } elseif ($scenario !== 'pending') {
                    [$order, $receipt] = app(Receiving::class)->receive($gym, $order, $order->lines()->get()->map(fn ($line) => [
                        'purchase_order_line_id' => $line->id, 'quantity' => $scenario === 'closed_short' ? 2 : 4,
                    ])->all(), $owner, today(), 'Counted and checked against the delivery slip.');
                    if ($scenario === 'closed_short') {
                        $ordering->closeShort($order, 'Supplier discontinued the remaining size; accept the delivered quantity only.', $owner);
                    }
                    if ($scenario === 'completed_paid') {
                        app(Payables::class)->pay($supplier, $receipt->total, [
                            'method' => 'bank', 'paid_on' => today()->toDateString(),
                            'reference' => 'BPI-'.today()->format('ymd').'-'.$gym->id,
                            'note' => 'Delivery paid in full.', 'purchase_order_id' => $order->id,
                        ], $owner);
                    }
                }
            });
        }
    }

    private function seedCustomerScenarios(Gym $gym, User $owner, Customer $lead): void
    {
        foreach (['walk_in', 'referral', 'social', 'website', 'event', 'other'] as $index => $source) {
            $gym->customers()->create([
                'name' => $this->personName($gym->id, 22 + $index), 'email' => $this->personEmail($gym->id, 22 + $index),
                'phone' => $this->mobile('inquiry-'.$gym->id.'-'.$source), 'source' => $source,
                'inquiry' => ['Asked about weekend beginner classes.', 'Referred by a colleague who attends evening classes.', 'Asked about trial availability after seeing the class timetable.', 'Requested monthly rates and a tour of the training floor.', 'Met the coaches at a community sports event.', 'Community centre coordinator asked about a group booking.'][$index],
                'assigned_to' => $owner->id, 'status' => 'open', 'created_by' => $owner->id,
            ]);
        }
        foreach (['phone', 'sms', 'email', 'messenger', 'walk_in', 'other'] as $index => $channel) {
            Carbon::withTestNow(now()->subDays(2)->setTime(10 + $index, 0), fn () => $lead->interactions()->create([
                'kind' => 'interaction', 'channel' => $channel, 'occurred_at' => now(), 'recorded_by' => $owner->id,
                'body' => ['Discussed trial class options.', 'Sent the opening hours and address.', 'Sent the membership rate sheet.', 'Confirmed that a beginner place is available.', 'Visited reception to ask about protective gear.', 'Community centre coordinator delivered the group booking enquiry.'][$index],
            ]));
        }
    }

    private function seedHrScenarios(Gym $gym, User $owner, array $employees): void
    {
        $book = app(StaffAttendanceBook::class);
        $person = $employees[0];
        $day = today()->subMonthsNoOverflow(4)->startOfMonth();
        $dates = [];
        while (count($dates) < 6) {
            if (app(WorkingDays::class)->kind($gym, $person, $day)->value === 'working') {
                $dates[] = $day->copy();
            }
            $day->addDay();
        }
        foreach ($dates as $index => $date) {
            $schedule = $book->scheduleFor($gym, $person, $date);
            Carbon::withTestNow($date->copy()->setTime(23, 0), function () use ($gym, $owner, $person, $book, $date, $schedule, $index) {
                $start = Carbon::parse($date->toDateString().' '.$schedule['start']);
                $end = Carbon::parse($date->toDateString().' '.$schedule['end']);
                if ($index === 0) {
                    Carbon::withTestNow($start, fn () => $book->clockIn($gym, $person));
                    Carbon::withTestNow($end, fn () => $book->clockOut($gym, $person));
                } else {
                    $row = $book->deskEntry($gym, $person, [
                        'work_date' => $date->toDateString(),
                        'clock_in_time' => $index === 2 ? null : $start->copy()->addMinutes($index === 1 ? 22 : 0)->format('H:i'),
                        'clock_out_time' => in_array($index, [2, 3], true) ? null : $end->copy()->addMinutes($index === 4 ? 60 : ($index === 5 ? -30 : 0))->format('H:i'),
                        'note' => match ($index) {
                            1 => 'Road closure delayed the morning commute.',
                            2 => 'Unable to attend shift due to a family emergency.',
                            3 => 'Clock-out not recorded; awaiting the signed shift log.',
                            4 => 'Stayed to prepare equipment for the next beginner intake.',
                            default => 'Left thirty minutes early for a clinic appointment.',
                        },
                    ], $owner);
                    if ($index === 2) {
                        $row->update(['source' => 'system']);
                    } elseif ($index === 3) {
                        $row->update(['missing_clock_out' => true]);
                    } elseif ($index === 5) {
                        $book->correct($gym, $row, ['clock_in_time' => $start->format('H:i')], $owner, 'Verified shift start against the reception handover log.');
                    }
                }
            });
        }
        foreach (['pending', 'approved', 'rejected'] as $index => $status) {
            $date = $dates[$index];
            if ($index === 1) {
                $date = $dates[5]->copy()->addDay();
                while (app(WorkingDays::class)->kind($gym, $person, $date)->value !== 'rest') {
                    $date->addDay();
                }
            }
            Carbon::withTestNow($date->copy()->setTime(19, 0), fn () => $gym->overtimeRequests()->create([
                'user_id' => $person->id, 'work_date' => $date->toDateString(),
                'kind' => ['after_shift', 'rest_day', 'manual'][$index], 'requested_minutes' => [60, 120, 45][$index],
                'approved_minutes' => $status === 'approved' ? 120 : null, 'status' => $status,
                'reason' => ['Prepared equipment after the evening classes.', 'Helped run the interclub weigh-in on a rest day.', 'Requested extra time for stock checking.'][$index],
                'decided_by' => $status === 'pending' ? null : $owner->id,
                'decided_at' => $status === 'pending' ? null : now()->addDay(),
                'decision_note' => match ($status) {
                    'approved' => 'Confirmed event duty and signed attendance log.',
                    'rejected' => 'Stock check was completed within the regular shift.',
                    default => null,
                },
            ]));
        }
        foreach (['commendation', 'concern', 'review', 'general'] as $index => $kind) {
            Carbon::withTestNow(now()->subDays(10 - $index)->setTime(9, 0), fn () => $gym->performanceNotes()->create([
                'user_id' => $employees[$index % count($employees)]->id, 'author_id' => $owner->id,
                'noted_on' => today()->toDateString(), 'kind' => $kind,
                'body' => ['Handled the beginner intake calmly and checked every equipment issue before class.', 'Two recent late starts affected reception coverage. Agreed to confirm transport plans before the morning shift.', 'Quarterly review: member feedback is positive. Next goal is more consistent session notes.', 'Completed refresher briefing on first aid locations and emergency contact procedures.'][$index],
            ]));
        }
    }

    private function seedSubscriptionStates(): void
    {
        $plan = SubscriptionPlan::where('name', 'Elite')->firstOrFail();
        foreach (Gym::where('status', 'approved')->orderBy('id')->get() as $index => $gym) {
            $gym->forceFill(match ($index) {
                0 => ['trial_ends_at' => now()->addDays(18)],
                1, 4 => ['trial_ends_at' => now()->subDays(90), 'subscription_plan_id' => $plan->id, 'paid_until' => now()->addDays(22)],
                2 => ['trial_ends_at' => now()->subDays(30)],
                default => ['trial_ends_at' => null],
            })->save();
            if (in_array($index, [1, 4], true)) {
                SubscriptionPayment::create([
                    'gym_id' => $gym->id, 'subscription_plan_id' => $plan->id, 'plan_name' => $plan->name,
                    'amount' => $plan->monthly_price, 'paid_by' => $gym->applicant_id,
                    'paymongo_checkout_id' => 'cs_'.substr(hash('sha256', 'subscription-'.$gym->id), 0, 24),
                    'paymongo_payment_id' => 'pay_'.substr(hash('sha256', 'subscription-'.$gym->id), 0, 24),
                    'period_starts_at' => now()->subDays(8), 'period_ends_at' => $gym->paid_until,
                    'created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8),
                ]);
            }
        }
    }
}
