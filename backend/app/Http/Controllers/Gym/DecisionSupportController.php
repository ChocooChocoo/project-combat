<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\DecisionSupport\Period;
use App\Support\DecisionSupport\TrainingDashboards;
use App\Support\DecisionSupport\MembershipMetrics;
use App\Support\DecisionSupport\CustomerMetrics;
use App\Support\DecisionSupport\BusinessDashboards;
use App\Support\DecisionSupport\Trends;
use App\Support\DecisionSupport\Forecast;
use App\Support\DecisionSupport\Problems;
use App\Support\DecisionSupport\Suggestions;
use App\Support\DecisionSupport\Sustainability;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DecisionSupportController extends Controller
{
    public function options(Gym $gym): JsonResponse
    {
        $today = Carbon::today('Asia/Manila');

        return response()->json([
            'today' => $today->toDateString(),
            'timezone' => 'Asia/Manila',
            'default_range' => ['from' => $today->copy()->startOfMonth()->toDateString(), 'to' => $today->toDateString()],
            'views' => ['attendance', 'memberships', 'athletes', 'services', 'customers', 'employees',
                'money', 'stock', 'trends', 'forecast', 'problems', 'sustainability'],
            'thresholds' => config('decision_support'),
        ]);
    }

    private function meta(Period $period, bool $hasData, array $notes = []): array
    {
        return [
            'from' => $period->from(), 'to' => $period->to(), 'timezone' => 'Asia/Manila',
            'generated_at' => Carbon::now('Asia/Manila')->toIso8601String(),
            'has_data' => $hasData, 'notes' => $notes,
        ];
    }

    /** (R-141) read-only; each action calls an existing endpoint after the owner confirms. */
    public function suggestions(Gym $gym, Suggestions $suggestions): JsonResponse
    {
        return response()->json($suggestions->forGym($gym));
    }

    public function attendance(Request $request, Gym $gym, TrainingDashboards $training): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $training->attendance($gym, $period);

        return response()->json(['meta' => $this->meta($period, count($data['classes']) > 0 || array_sum(array_column($data['weekdays'], 'gym_visits')) > 0, [
            'Attendance uses present, late, absent and no-show markers, including records on subsequently cancelled bookings or sessions. Gym visits are separate from class rates.',
            'Class grouping uses current session/service records. Awaiting and unknown markers are excluded from attendance denominators. Peaks count attendances and visits, not unique people.',
        ]), ...$data]);
    }

    public function athletes(Request $request, Gym $gym, TrainingDashboards $training): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $training->athletes($gym, $period);

        return response()->json(['meta' => $this->meta($period, count($data['results']) > 0 || $data['summary']['attending_athletes'] > 0, [
            'coach based on attendance: each athlete is assigned to their most attended coach in this period; ties use latest attendance then lowest coach ID. This link is inferred from attendance, not a causal claim or the person who recorded progress. Athletes without attendance remain unassigned.',
            'Training effectiveness is distinct attendees with at least one progress entry divided by distinct attendees. Present/late markers include subsequently cancelled bookings or sessions. Results are shown as recorded, not inferred wins or losses.',
        ]), ...$data]);
    }

    public function services(Request $request, Gym $gym, TrainingDashboards $training): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $training->services($gym, $period);

        return response()->json(['meta' => $this->meta($period, count($data['services']) > 0 || $data['summary']['sessions'] > 0 || $data['private_coaching']['offered_sessions'] > 0, [
            'Occupancy uses current requested and booked places on noncancelled scheduled sessions; it is not a historical booking snapshot. Confirmed bookings are shown separately. Zero demand can produce tied most/least service rankings.',
            'Summary and ordinary classes exclude private coaching. Service, coach and room rows include it. Fill is pooled held places over valid offered capacity; zero-capacity anomalies are disclosed and excluded from the rate. Private booking rate uses offered occurrences.',
            'Delivered means at least one present/late marker. Workload counts offered/delivered sessions and actual minutes, not availability-hours utilization, payroll eligibility or room profit.',
        ]), ...$data]);
    }

    public function memberships(Request $request, Gym $gym, MembershipMetrics $memberships): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $memberships->forPeriod($gym, $period);

        return response()->json(['meta' => $this->meta($period, count(array_filter($data['summary'], fn ($value, $key) => ! str_ends_with($key, '_rate') && $value > 0, ARRAY_FILTER_USE_BOTH)) > 0, [
            'New memberships use first activation events, not application dates or current status. Activity counts membership events, not unique member users.',
            'Retention uses the opening active/frozen event-state cohort minus distinct opening memberships expired or cancelled in the period. Frozen memberships remain retained; reactivation does not erase period churn.',
            'Renewed means renewal actions, including expired renewals awaiting payment. Renewal rate is renewals divided by renewals plus expirations. Renewals and unfreezing alone do not reopen expired membership intervals.',
            'Missing activation history is a current data-quality count for active/frozen records. Historical membership stock is never guessed from mutable dates or current status.',
        ]), ...$data]);
    }

    public function customers(Request $request, Gym $gym, CustomerMetrics $customers): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $customers->forPeriod($gym, $period);

        return response()->json(['meta' => $this->meta($period, count(array_filter($data['summary'], fn ($value, $key) => $key !== 'conversion_rate' && $value > 0, ARRAY_FILTER_USE_BOTH)) > 0, [
            'Conversion uses leads created in this range with linked membership activation evidence in the same range, linked before period end. Current CRM stage and membership status do not replace activation evidence.',
            'Members gained from CRM counts distinct member users with a first membership activation in the range and a customer linked before period end, including leads created earlier.',
            'Concerns raised uses occurrence dates. Open at period end includes opening backlog and concerns resolved on or after the next day. Resolved in period uses resolution dates, regardless of when raised. Feedback uses occurrence dates.',
        ]), ...$data]);
    }

    public function employees(Request $request, Gym $gym, BusinessDashboards $business): JsonResponse
    {
        $period = Period::fromRequest($request);
        $payVisible = $request->user()->hasPermission($gym, 'payroll.runs.view');
        $data = $business->employees($gym, $period, $payVisible);

        return response()->json(['meta' => $this->meta($period, count($data['attendance']) > 0 || count($data['workload']) > 0 || $data['payroll']['totals']['count'] > 0, [
            'Staff attendance uses recorded work-date status and stored minutes. Missing rows are not assumed present or backfilled. Late rate uses present plus late records as its denominator.',
            'Operational coach workload includes offered/delivered sessions and minutes, not payroll-payable eligibility or availability hours. Payroll uses released lines with run end dates in range, not cached run totals or overlap proration.',
            'Aggregate payroll is visible to decision-support holders. Individual pay appears only with payroll.runs.view; HR or finance permission alone does not reveal it.',
        ]), ...$data]);
    }

    public function money(Request $request, Gym $gym, BusinessDashboards $business): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $business->money($gym, $period);
        $hasData = $data['receivables']['debtor_count'] > 0
            || $period->apply(DB::table('payments')->where('gym_id', $gym->id), 'paid_at')->exists()
            || $period->apply(DB::table('expenses')->where('gym_id', $gym->id), 'incurred_on', false)->exists()
            || $period->apply(DB::table('supplier_payments')->where('gym_id', $gym->id), 'paid_on', false)->exists();

        return response()->json(['meta' => $this->meta($period, $hasData, [
            'Revenue and income against expenses use the selected date range and signed recorded amounts, including corrections. Income types are authoritative; membership fees are not allocated to services. Service classification uses current linked records; cancelled paid sessions remain included.',
            'Net revenue share is signed category revenue divided by positive total net revenue. Negative shares and shares above 100% are possible. A nonpositive total has no defined share.',
            'Outstanding balances as of today; not a historical balance',
            'Outstanding balances sum positive current source debts without offsetting another source with overpayments. This current-snapshot limitation was accepted by the user on 7 November 2026; selected dates do not reconstruct historical charges.',
            'Income against expenses includes accrual expenses; supplier payments are cash out, not a second expense. Payments linked to both membership and booking are excluded from service allocation, but retained once in income totals and disclosed as anomalies.',
        ]), ...$data]);
    }

    public function stock(Request $request, Gym $gym, BusinessDashboards $business): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $business->stock($gym, $period);

        return response()->json(['meta' => $this->meta($period, $data['summary']['items'] > 0 || count($data['purchasing']['requests']) > 0 || count($data['purchasing']['orders']) > 0 || $data['purchasing']['receipts']['count'] > 0, [
            'Stock levels are active-item quantities as of today, independent of the selected range. Low stock means at or below the stated minimum. Counts are items, never a sum of incompatible quantity units; archived items are excluded.',
            'Purchasing shows current stored status of requests/orders created in the selected range, not historical status at that time. Receipts use their own received date. Reading reports does not refresh procurement status.',
        ]), ...$data]);
    }

    public function trends(Request $request, Gym $gym, Trends $trends): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $trends->forPeriod($gym, $period);

        return response()->json(['meta' => $this->meta($period, count(array_filter($data['first_records'])) > 0 || $data['closing_active'] > 0, [
            'Trends use their own trailing '.config('decision_support.trends.months').'-month window through the earlier of selected end and today; selected start does not shorten it. The final month is clipped to the effective end day.',
            'First-record dates disclose actual source history; zero buckets do not invent earlier records. Attendance uses known markers, including subsequently cancelled bookings/sessions. Gym visits are not in class attendance rates.',
            'Membership activity uses lifecycle events and first activations. Closing active/frozen memberships are event-state stock at each bucket end, not a sum of monthly stocks. Earlier opening activation history remains included.',
        ]), ...$data]);
    }

    public function sustainability(Request $request, Gym $gym, Sustainability $sustainability): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $sustainability->forPeriod($gym, $period);
        $hasData = $data['has_data'];
        unset($data['has_data']);
        $forecastNotes = [
            'Cash-flow forecast uses its own completed-month history, capped at today, not the selected start. Cash out includes manual/payroll expenses and supplier payments, not goods-receipt accrual expense again.',
            'Straight-line least-squares uses at most '.config('decision_support.forecast.history_months').' observed calendar months and projects '.config('decision_support.forecast.ahead_months').' months. Below '.config('decision_support.forecast.minimum_trend_months').' history months: average, not enough history. No history gives no projections.',
            'R² confidence (fit) is historical fit on a 0..1 scale, not probability or certainty. Incoming and outgoing forecasts round in centavos and clamp independently at zero; net can be negative. Historical selections are not an audit of information known then.',
        ];
        $data['cash_flow_forecast'] = ['meta' => $this->meta($period, count($data['cash_flow_forecast']['history']) > 0, $forecastNotes), ...$data['cash_flow_forecast']];

        return response()->json(['meta' => $this->meta($period, $hasData, [
            'Five separate measures, not a composite sustainability score. Financial actuals, revenue mix, retention and offered-session utilization use the selected dates. Monthly finance buckets are clipped to the selected first and last days.',
            'Revenue mix uses signed net income by authoritative income type and session-linked service. Negative shares or shares above 100% can occur; shares are undefined when total net income is nonpositive. Membership fees are not allocated to services; dual-linked payments remain in income totals but not service allocation.',
            'Retention uses the opening active/frozen event-state cohort minus distinct opening memberships expired or cancelled in range. Frozen memberships remain retained; reactivation does not erase churn. Renewal actions include expired renewals awaiting payment; renewal rate is actions divided by actions plus expirations. Missing activation history is disclosed, never guessed.',
            'Class and room use reflect current requested/booked places on scheduled noncancelled offerings, not historical occupancy or availability-hours utilization. Class fill excludes private coaching; room use includes it. Fill pools valid offered capacity; invalid-capacity sessions are disclosed. Delivered means a present/late marker, not payroll eligibility or room profit.',
            'Budget and actual use the whole calendar month containing selected end, including days outside the selected range. Difference is budget minus actual; percentage is actual divided by budget and is unavailable for zero budget. No daily proration.',
            ...$forecastNotes,
        ]), ...$data]);
    }

    public function problems(Request $request, Gym $gym, Problems $problems): JsonResponse
    {
        $period = Period::fromRequest($request);
        $data = $problems->forPeriod($gym, $period);
        $hasData = $data['has_data'];
        unset($data['has_data']);

        return response()->json(['meta' => $this->meta($period, $hasData, [
            'Rules use completed scheduled sessions starting in the selected range. Cancelled, ongoing and future sessions are excluded. Known attendance outcomes alone enter attendance and no-show denominators; unmarked booked places are shown separately.',
            'Falling attendance compares the latest '.config('decision_support.falling_attendance.window_sessions').' occurrences with the preceding same number, including unmarked occurrences. Both windows require recorded outcomes; rates are pooled and thresholds compare unrounded figures.',
            'Low-use slots use the arithmetic mean of individual valid-capacity session fill rates, not pooled fill. Held seats use current requested and booked places, not a historical occupancy snapshot. Class grouping uses current session/service records.',
            'No triggered rules is not a grade for the whole business. Thresholds below come from server configuration; reading this view sends no alerts and changes no records.',
        ]), ...$data]);
    }

    public function forecast(Request $request, Gym $gym, Forecast $forecast): JsonResponse
    {
        $period = Period::fromRequest($request);
        $validated = $request->validate(['basis' => ['sometimes', 'in:income_expense,cash_flow']]);
        $data = $forecast->forPeriod($gym, $period, $validated['basis'] ?? 'income_expense');

        return response()->json(['meta' => $this->meta($period, count($data['history']) > 0, [
            'Forecast uses at most '.config('decision_support.forecast.history_months').' completed months ending before the unfinished month, capped at today, and projects '.config('decision_support.forecast.ahead_months').' calendar months. Selected start does not shorten this own history window.',
            'Straight-line least-squares independently fits incoming and outgoing series. Below '.config('decision_support.forecast.minimum_trend_months').' observed calendar months the method is average, not enough history. No history gives no projections; real zero gaps and trailing months count, prehistory does not.',
            'R² confidence (fit) measures historical fit on a 0..1 scale, not probability, certainty or a prediction interval. Incoming/outgoing forecasts are rounded in centavos and clamped at zero independently; net may be negative.',
            'Income/expense basis uses accrual expenses. Cash-flow basis uses manual/payroll expenses and supplier payments, not goods-receipt expense as an additional cash out. Historical end selections do not claim an audit of information known at that time.',
        ]), ...$data]);
    }
}
