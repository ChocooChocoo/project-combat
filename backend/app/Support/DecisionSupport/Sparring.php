<?php

namespace App\Support\DecisionSupport;

use App\Enums\MembershipStatus;
use App\Models\AthleteProfile;
use App\Models\Gym;
use App\Models\User;

/** (R-140) Suggests sparring partners for one athlete from weight, height, level, experience and record. */
class Sparring
{
    // ponytail: simple weighted score; add sport and gender filters if coaches ask
    public function matchesFor(Gym $gym, User $user): array
    {
        $target = $gym->athleteProfiles()->where('user_id', $user->id)->firstOrFail();
        $activeIds = $gym->memberships()->where('status', MembershipStatus::Active)->pluck('user_id')->unique();
        $candidates = $gym->athleteProfiles()->whereIn('user_id', $activeIds)->where('user_id', '!=', $user->id)->get();
        $users = User::whereIn('id', $candidates->pluck('user_id'))->get()->keyBy('id');

        // Minors only spar minors and adults only adults; an unknown birthdate is not filtered.
        $minor = fn (?User $person) => $person?->birthdate ? $person->birthdate->age < User::ADULT_AGE : null;
        $targetMinor = $minor($user);

        $matches = $candidates
            ->reject(fn (AthleteProfile $other) => $targetMinor !== null
                && ($otherMinor = $minor($users[$other->user_id] ?? null)) !== null && $otherMinor !== $targetMinor)
            ->map(fn (AthleteProfile $other) => $this->score($target, $other, $users[$other->user_id] ?? null))
            ->filter()->sortBy([
                // with a known target weight, candidates without a weight go last
                [fn ($x, $y) => ($target->weight_kg !== null && $x['weight_kg'] === null) <=> ($target->weight_kg !== null && $y['weight_kg'] === null), 'asc'],
                ['score', 'desc'],
                ['name', 'asc'],
            ])->take(config('sparring.limit'))->values()->all();

        return [
            'athlete' => $this->payload($target),
            'matches' => $matches,
            'notes' => array_values(array_filter([
                $target->weight_kg === null ? "Add this athlete's weight for safer matches." : null,
                $targetMinor ? 'This athlete is under 18, so only other athletes under 18 are suggested.' : null,
            ])),
        ];
    }

    public function payload(AthleteProfile $profile): array
    {
        return [
            'weight_kg' => $profile->weight_kg === null ? null : (float) $profile->weight_kg,
            'height_cm' => $profile->height_cm,
            'wins' => (int) $profile->wins,
            'losses' => (int) $profile->losses,
            'draws' => (int) $profile->draws,
            'experience_years' => $profile->experience_years,
        ];
    }

    private function score(AthleteProfile $a, AthleteProfile $b, ?User $person): ?array
    {
        if (! $person) {
            return null;
        }
        $w = config('sparring.weights');
        $points = 0.0;
        $available = 0;
        $reasons = [];

        if ($a->weight_kg !== null && $b->weight_kg !== null) {
            $gap = abs((float) $a->weight_kg - (float) $b->weight_kg);
            if ($gap > config('sparring.max_weight_gap_kg')) {
                return null;
            }
            $available += $w['weight'];
            $points += $w['weight'] * (1 - $gap / config('sparring.max_weight_gap_kg'));
            $reasons[] = rtrim(rtrim(number_format($gap, 1, '.', ''), '0'), '.').' kg apart';
        } elseif ($a->weight_kg !== null) {
            $reasons[] = 'Weight not recorded';
        }

        if ($a->height_cm !== null && $b->height_cm !== null) {
            $gap = abs($a->height_cm - $b->height_cm);
            $available += $w['height'];
            $points += $w['height'] * max(0, 1 - $gap / config('sparring.height_cap_cm'));
            $reasons[] = "{$gap} cm apart";
        }

        $levels = array_keys(config('offer.athlete_levels'));
        $levelA = array_search($a->level ?? $levels[0], $levels, true);
        $levelB = array_search($b->level ?? $levels[0], $levels, true);
        $apart = abs($levelA - $levelB);
        $available += $w['level'];
        $points += $w['level'] * ($apart === 0 ? 1 : ($apart === 1 ? 0.5 : 0));
        $reasons[] = $apart === 0 ? 'Same level: '.config("offer.athlete_levels.{$levels[$levelB]}") : ($apart === 1 ? 'One level apart' : 'Levels far apart');

        if ($a->experience_years !== null && $b->experience_years !== null) {
            $gap = abs($a->experience_years - $b->experience_years);
            $available += $w['experience'];
            $points += $w['experience'] * max(0, 1 - $gap / config('sparring.experience_cap_years'));
            $reasons[] = $gap === 0 ? 'Same experience' : "{$gap} ".($gap === 1 ? 'year' : 'years').' more experience';
        }

        $totalA = $a->wins + $a->losses + $a->draws;
        $totalB = $b->wins + $b->losses + $b->draws;
        if ($totalA > 0 && $totalB > 0) {
            $rateA = $a->wins / $totalA;
            $rateB = $b->wins / $totalB;
            $available += $w['record'];
            $points += $w['record'] * max(0, 1 - abs($rateA - $rateB) / config('sparring.record_cap'));
            $reasons[] = 'Win rates '.round($rateA * 100).'% and '.round($rateB * 100).'%';
        }

        return [
            'user_id' => $person->id,
            'name' => $person->fullName(),
            'score' => $available > 0 ? (int) round(100 * $points / $available) : 0,
            'reasons' => $reasons,
            'weight_kg' => $b->weight_kg === null ? null : (float) $b->weight_kg,
            'height_cm' => $b->height_cm,
            'level' => $b->level,
            'record' => "{$b->wins}-{$b->losses}-{$b->draws}",
            'experience_years' => $b->experience_years,
        ];
    }
}
