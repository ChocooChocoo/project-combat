<?php

namespace App\Support\DecisionSupport;

use App\Models\Gym;
use App\Support\Sql;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TrainingDashboards
{
    private const OUTCOMES = ['present', 'late', 'absent', 'no_show'];
    private const PROGRESS = ['promotion', 'assessment', 'competition', 'achievement'];

    public function sessions(Gym $gym, Period $period): Collection
    {
        $bookings = DB::table('session_bookings as b')->where('b.gym_id', $gym->id)
            ->select('b.training_session_id')->groupBy('b.training_session_id');
        foreach (self::OUTCOMES as $outcome) {
            $bookings->selectRaw("SUM(CASE WHEN b.attendance = ? THEN 1 ELSE 0 END) as {$outcome}", [$outcome]);
        }
        $bookings->selectRaw("SUM(CASE WHEN b.status IN ('requested','booked') THEN 1 ELSE 0 END) as held_seats")
            ->selectRaw("SUM(CASE WHEN b.status = 'booked' THEN 1 ELSE 0 END) as confirmed_bookings")
            ->selectRaw("SUM(CASE WHEN b.status = 'booked' AND b.attendance IS NULL THEN 1 ELSE 0 END) as unmarked_booked")
            ->selectRaw("SUM(CASE WHEN b.attendance IS NOT NULL AND b.attendance NOT IN ('present','late','absent','no_show') THEN 1 ELSE 0 END) as unknown_markers");

        return $period->apply(DB::table('training_sessions as s')->where('s.gym_id', $gym->id), 's.starts_at')
            ->leftJoinSub($bookings, 'b', 'b.training_session_id', '=', 's.id')
            ->leftJoin('gym_services as service', fn (JoinClause $join) => $join->on('service.id', '=', 's.gym_service_id')->where('service.gym_id', $gym->id))
            ->leftJoin('rooms as room', fn (JoinClause $join) => $join->on('room.id', '=', 's.room_id')->where('room.gym_id', $gym->id))
            ->leftJoin('users as coach', 'coach.id', '=', 's.coach_id')
            ->select(['s.id', 's.name', 's.type', 's.discipline', 's.level', 's.coach_id', 's.starts_at', 's.ends_at',
                's.status', 's.flagged_at', 's.capacity', 'service.id as service_id', 'service.name as service_name',
                'room.id as room_id', 'room.name as room_name', 'coach.first_name', 'coach.last_name'])
            ->selectRaw(Sql::weekday('s.starts_at').' as weekday, '.Sql::hour('s.starts_at').' as hour')
            ->addSelect(array_map(fn ($key) => DB::raw("COALESCE(b.{$key}, 0) as {$key}"),
                [...self::OUTCOMES, 'held_seats', 'confirmed_bookings', 'unmarked_booked', 'unknown_markers']))
            ->orderBy('s.starts_at')->orderBy('s.id')->get()->map(function ($source) {
                $row = (array) $source;
                foreach (['id', 'coach_id', 'capacity', 'weekday', 'hour', ...self::OUTCOMES,
                    'held_seats', 'confirmed_bookings', 'unmarked_booked', 'unknown_markers'] as $key) $row[$key] = (int) $row[$key];
                foreach (['service_id', 'room_id'] as $key) $row[$key] = $row[$key] === null ? null : (int) $row[$key];
                $row['coach_name'] = trim($row['first_name'].' '.$row['last_name']);
                unset($row['first_name'], $row['last_name']);
                $row['class_key'] = $row['service_id'] !== null ? 'service:'.$row['service_id']
                    : 'ad-hoc:'.sha1(json_encode([$row['type'], $row['name'], $row['discipline'], $row['level']]));
                $row['attended'] = $row['present'] + $row['late'];
                $row['marked'] = $row['attended'] + $row['absent'] + $row['no_show'];
                $row['awaiting_attendance'] = Carbon::parse($row['ends_at'])->lt(now()) ? $row['unmarked_booked'] : 0;
                $row['cancelled_session_markers'] = $row['status'] === 'cancelled' ? $row['marked'] : 0;
                $row['minutes'] = max(0, (int) Carbon::parse($row['starts_at'])->diffInMinutes(Carbon::parse($row['ends_at'])));

                return $row;
            });
    }

    public function attendance(Gym $gym, Period $period): array
    {
        $sessions = $this->sessions($gym, $period);
        $weekdays = array_map(fn ($day, $label) => ['weekday' => $day, 'label' => $label, 'class_attended' => 0, 'gym_visits' => 0, 'total' => 0],
            range(0, 6), ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
        $hours = array_map(fn ($hour) => ['hour' => $hour, 'label' => sprintf('%02d:00', $hour), 'class_attended' => 0, 'gym_visits' => 0, 'total' => 0], range(0, 23));
        foreach ($sessions as $session) {
            $weekdays[$session['weekday']]['class_attended'] += $session['attended'];
            $hours[$session['hour']]['class_attended'] += $session['attended'];
        }
        foreach (['weekday' => Sql::weekday('v.checked_in_at'), 'hour' => Sql::hour('v.checked_in_at')] as $kind => $expression) {
            $visits = $period->apply(DB::table('gym_visits as v')->where('v.gym_id', $gym->id), 'v.checked_in_at')
                ->selectRaw($expression.' as bucket, COUNT(*) as total')->groupByRaw($expression)->get();
            foreach ($visits as $visit) {
                if ($kind === 'weekday') $weekdays[(int) $visit->bucket]['gym_visits'] = (int) $visit->total;
                else $hours[(int) $visit->bucket]['gym_visits'] = (int) $visit->total;
            }
        }
        foreach ($weekdays as &$day) $day['total'] = $day['class_attended'] + $day['gym_visits'];
        unset($day);
        foreach ($hours as &$hour) $hour['total'] = $hour['class_attended'] + $hour['gym_visits'];
        unset($hour);
        $peaks = function (array $rows, string $key): array {
            $max = max(array_column($rows, 'total'));

            return $max > 0 ? array_values(array_column(array_filter($rows, fn ($row) => $row['total'] === $max), $key)) : [];
        };

        return [
            'summary' => $this->attendanceCounts($sessions),
            'classes' => $sessions->groupBy('class_key')->map(fn ($rows) => [
                ...$this->classRef($rows->first()), ...$this->attendanceCounts($rows), 'sessions' => $rows->count(),
            ])->values()->all(),
            'weekdays' => $weekdays, 'hours' => $hours,
            'peaks' => ['weekdays' => $peaks($weekdays, 'weekday'), 'hours' => $peaks($hours, 'hour')],
        ];
    }

    public function athletes(Gym $gym, Period $period): array
    {
        $attended = $period->apply(DB::table('session_bookings as b')->where('b.gym_id', $gym->id)
            ->join('training_sessions as s', fn (JoinClause $join) => $join->on('s.id', '=', 'b.training_session_id')->where('s.gym_id', $gym->id))
            ->whereIn('b.attendance', ['present', 'late']), 's.starts_at')
            ->leftJoin('users as coach', 'coach.id', '=', 's.coach_id')
            ->select('b.user_id', 's.coach_id', 'coach.first_name', 'coach.last_name')
            ->selectRaw('COUNT(DISTINCT s.id) as occurrences, MAX(s.starts_at) as latest')
            ->groupBy('b.user_id', 's.coach_id', 'coach.first_name', 'coach.last_name')
            ->orderByDesc('occurrences')->orderByDesc('latest')->orderBy('s.coach_id')->get();
        $primary = [];
        foreach ($attended as $row) {
            $primary[(int) $row->user_id] ??= ['coach_id' => $row->coach_id === null ? null : (int) $row->coach_id,
                'name' => trim($row->first_name.' '.$row->last_name) ?: 'Unassigned'];
        }
        $entries = $period->apply(DB::table('athlete_entries as e')->where('e.gym_id', $gym->id)->whereIn('e.type', self::PROGRESS), 'e.occurred_on', false)
            ->select('e.user_id', 'e.type', 'e.discipline', 'e.title', 'e.division', 'e.result')
            ->selectRaw(Sql::month('e.occurred_on').' as period, COUNT(*) as count')
            ->groupBy('e.user_id', 'e.type', 'e.discipline', 'e.title', 'e.division', 'e.result')->groupByRaw(Sql::month('e.occurred_on'))->get();
        $counts = array_fill_keys(self::PROGRESS, 0);
        $summary = $counts;
        $buckets = array_fill_keys($period->monthKeys(), null);
        foreach ($buckets as $month => $_) $buckets[$month] = ['period' => $month, ...$counts];
        $disciplines = []; $coaches = []; $results = []; $progress = [];
        foreach ($primary as $userId => $coach) {
            $key = $coach['coach_id'] ?? 'unassigned';
            $coaches[$key] ??= [...$coach, ...$counts, 'attending_athletes' => 0, 'athletes_with_progress' => 0];
            $coaches[$key]['attending_athletes']++;
        }
        foreach ($entries as $entry) {
            $coach = $primary[(int) $entry->user_id] ?? ['coach_id' => null, 'name' => 'Unassigned'];
            $coachKey = $coach['coach_id'] ?? 'unassigned';
            $coaches[$coachKey] ??= [...$coach, ...$counts, 'attending_athletes' => 0, 'athletes_with_progress' => 0];
            $disciplineKey = json_encode($entry->discipline);
            $disciplines[$disciplineKey] ??= ['discipline' => $entry->discipline, ...$counts];
            $count = (int) $entry->count;
            $summary[$entry->type] += $count;
            $buckets[$entry->period][$entry->type] += $count;
            $disciplines[$disciplineKey][$entry->type] += $count;
            $coaches[$coachKey][$entry->type] += $count;
            if (isset($primary[(int) $entry->user_id])) $progress[(int) $entry->user_id] = $coachKey;
            $result = ['period' => $entry->period, 'coach_id' => $coach['coach_id'], 'coach_name' => $coach['name'],
                'type' => $entry->type, 'discipline' => $entry->discipline, 'title' => $entry->title, 'division' => $entry->division, 'result' => $entry->result];
            $resultKey = json_encode($result);
            $results[$resultKey] ??= [...$result, 'count' => 0];
            $results[$resultKey]['count'] += $count;
        }
        foreach ($progress as $coachKey) $coaches[$coachKey]['athletes_with_progress']++;
        foreach ($coaches as &$coach) $coach['effectiveness_rate'] = Metrics::rate($coach['athletes_with_progress'], $coach['attending_athletes']);
        unset($coach);
        $summary += ['attending_athletes' => count($primary), 'athletes_with_progress' => count($progress),
            'effectiveness_rate' => Metrics::rate(count($progress), count($primary))];

        return ['attribution' => 'coach_based_on_attendance', 'summary' => $summary,
            'buckets' => array_values($buckets), 'disciplines' => array_values($disciplines),
            'coaches' => array_values($coaches), 'results' => array_values($results)];
    }

    public function services(Gym $gym, Period $period): array
    {
        $sessions = $this->sessions($gym, $period)->where('status', 'scheduled');
        $ordinary = $sessions->where('type', '!=', 'private_coaching');
        $services = DB::table('gym_services')->where('gym_id', $gym->id)->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(fn ($service) => ['service_id' => (int) $service->id, 'name' => $service->name,
                ...$this->useCounts($sessions->where('service_id', (int) $service->id))]);
        if ($sessions->whereNull('service_id')->isNotEmpty()) $services->push(['service_id' => null, 'name' => 'Unassigned', ...$this->useCounts($sessions->whereNull('service_id'))]);
        $services = $services->sort(fn ($a, $b) => strcmp($a['name'], $b['name']) ?: (($a['service_id'] ?? 0) <=> ($b['service_id'] ?? 0)))->values();
        $extreme = fn ($count) => $services->where('held_seats', $count)->map(fn ($row) => array_intersect_key($row, array_flip(['service_id', 'name', 'held_seats'])))->values()->all();
        $private = $sessions->where('type', 'private_coaching');

        return ['summary' => $this->useCounts($ordinary), 'services' => $services->all(),
            'most_booked' => $services->isEmpty() ? [] : $extreme($services->max('held_seats')),
            'least_booked' => $services->isEmpty() ? [] : $extreme($services->min('held_seats')),
            'classes' => $ordinary->groupBy('class_key')->map(fn ($rows) => [...$this->classRef($rows->first()), ...$this->useCounts($rows)])->values()->all(),
            'coaches' => $sessions->whereNotNull('coach_id')->groupBy('coach_id')->map(fn ($rows) => [
                'coach_id' => $rows->first()['coach_id'], 'name' => $rows->first()['coach_name'], ...$this->useCounts($rows),
            ])->values()->all(),
            'rooms' => $sessions->whereNotNull('room_id')->groupBy('room_id')->map(fn ($rows) => [
                'room_id' => $rows->first()['room_id'], 'name' => $rows->first()['room_name'], ...$this->useCounts($rows),
            ])->values()->all(),
            'private_coaching' => ['offered_sessions' => $private->count(), 'booked_sessions' => $private->where('held_seats', '>', 0)->count(),
                'booking_rate' => Metrics::rate($private->where('held_seats', '>', 0)->count(), $private->count())],
        ];
    }

    private function attendanceCounts(Collection $rows): array
    {
        $result = [];
        foreach ([...self::OUTCOMES, 'attended', 'marked', 'awaiting_attendance', 'unknown_markers', 'cancelled_session_markers'] as $key) $result[$key] = (int) $rows->sum($key);

        return [...$result, 'attendance_rate' => Metrics::rate($result['attended'], $result['marked']),
            'no_show_rate' => Metrics::rate($result['no_show'], $result['marked'])];
    }

    private function useCounts(Collection $rows): array
    {
        $valid = $rows->where('capacity', '>', 0);
        $delivered = $rows->where('attended', '>', 0);

        return ['sessions' => $rows->count(), 'delivered_sessions' => $delivered->count(),
            'scheduled_minutes' => (int) $rows->sum('minutes'), 'delivered_minutes' => (int) $delivered->sum('minutes'),
            'held_seats' => (int) $rows->sum('held_seats'), 'confirmed_bookings' => (int) $rows->sum('confirmed_bookings'),
            'capacity' => (int) $valid->sum('capacity'), 'invalid_capacity_sessions' => $rows->count() - $valid->count(),
            'fill_rate' => Metrics::rate($valid->sum('held_seats'), $valid->sum('capacity'))];
    }

    private function classRef(array $row): array
    {
        return ['class_key' => $row['class_key'], 'service_id' => $row['service_id'], 'name' => $row['name']];
    }
}
