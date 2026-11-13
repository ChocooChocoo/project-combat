<?php

namespace App\Http\Controllers\Gym;

use App\Support\DateRange;
use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Concerns\PresentsMemberships;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Payment;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Support\BookingLedger;
use App\Support\MembershipLedger;
use App\Support\Paging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-34: the payments desk. Entries are never edited; a correction is its own entry. */
class PaymentController extends Controller
{
    use PresentsMemberships;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'membership_id' => ['nullable', 'integer'],
            ...DateRange::rules(),
            ...Paging::rules(),
        ]);

        $query = $gym->payments()->with(['user', 'receivedBy']);

        DateRange::apply($query, $data, 'paid_at');

        if (! empty($data['membership_id'])) {
            $query->where('membership_id', $data['membership_id']);
        }

        [$rows, $meta] = Paging::rows($query->orderByDesc('paid_at')->orderByDesc('id'), $request, 500);
        $payments = $rows->map(fn (Payment $payment) => $this->paymentPayload($payment))->values();

        return response()->json(['payments' => $payments, ...($meta ? ['meta' => $meta] : [])]);
    }

    public function owedBookings(Gym $gym, BookingLedger $ledger): JsonResponse
    {
        $bookings = SessionBooking::with(['user', 'session'])->where('status', BookingStatus::Booked)->get()
            ->filter(fn ($booking) => $ledger->balance($booking) > 0)
            ->map(fn ($booking) => [
                'id' => $booking->id, 'balance' => number_format($ledger->balance($booking), 2, '.', ''),
                'athlete' => ['id' => $booking->user_id, 'name' => trim("{$booking->user->first_name} {$booking->user->last_name}")],
                'session' => ['name' => $booking->session->name, 'starts_at' => $booking->session->starts_at->format('Y-m-d H:i:s')],
            ])->values();

        return response()->json(['bookings' => $bookings]);
    }

    public function store(Request $request, Gym $gym, MembershipLedger $ledger, BookingLedger $bookingLedger): JsonResponse
    {
        $data = $request->validate([
            'membership_id' => ['nullable', 'required_without:session_booking_id', 'prohibits:session_booking_id', 'integer', Rule::exists('memberships', 'id')->where('gym_id', $gym->id)],
            'session_booking_id' => ['nullable', 'required_without:membership_id', 'prohibits:membership_id', 'integer', Rule::exists('session_bookings', 'id')->where('gym_id', $gym->id)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999', ...($request->filled('session_booking_id') ? ['decimal:0,2'] : [])],
            'method' => ['required', Rule::in(array_keys(config('membership.desk_methods')))],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['membership_id.exists' => 'Choose a member of this gym.']);

        if (! empty($data['session_booking_id'])) {
            $payment = DB::transaction(function () use ($data, $request, $bookingLedger) {
                $booking = SessionBooking::findOrFail($data['session_booking_id']);
                TrainingSession::lockForUpdate()->findOrFail($booking->training_session_id);
                $booking = SessionBooking::lockForUpdate()->findOrFail($booking->id);
                abort_unless($booking->status === BookingStatus::Booked, 409, 'Only booked sessions can be paid.');
                $balance = $bookingLedger->balance($booking);
                if ($balance <= 0 || round((float) $data['amount'], 2) > $balance) {
                    throw ValidationException::withMessages(['amount' => 'The amount must not exceed the balance owed.']);
                }

                return $bookingLedger->recordPayment($booking, (float) $data['amount'], PaymentMethod::from($data['method']), $request->user(), ['note' => $data['note'] ?? null]);
            });

            return response()->json(['payment' => $this->paymentPayload($payment->load(['user', 'receivedBy']))], 201);
        }

        $membership = $gym->memberships()->with('gym')->findOrFail($data['membership_id']);

        // R-33: money is taken only after approval, and taking it never approves anything.
        abort_unless(
            in_array($membership->status, [MembershipStatus::AwaitingPayment, MembershipStatus::Active, MembershipStatus::Frozen], true),
            409,
            'Payments can be recorded only for approved memberships.',
        );

        $payment = DB::transaction(function () use ($membership, $ledger, $data, $request) {
            $membership = $membership->newQuery()->lockForUpdate()->findOrFail($membership->id);
            $balance = $ledger->balance($membership);
            if ($balance <= 0 || round((float) $data['amount'], 2) > $balance) {
                throw ValidationException::withMessages(['amount' => 'The amount must not exceed the balance owed.']);
            }

            return $ledger->recordPayment(
                $membership, (float) $data['amount'], PaymentMethod::from($data['method']),
                $request->user(), ['note' => $data['note'] ?? null],
            );
        });

        return response()->json(['payment' => $this->paymentPayload($payment->load(['user', 'receivedBy']))], 201);
    }

    /** Reverses a wrong entry in full. The right amount is then recorded as a new payment. */
    public function correct(Request $request, Gym $gym, int $payment, MembershipLedger $ledger, BookingLedger $bookingLedger): JsonResponse
    {
        $original = $gym->payments()->findOrFail($payment);

        abort_if($original->corrects_id !== null || (float) $original->amount <= 0, 409, 'Only a payment can be corrected, not a correction or refund.');
        abort_if($original->membership_id === null && $original->session_booking_id === null, 409, 'Desk sales cannot be corrected here.');
        abort_if($original->method === PaymentMethod::Paymongo, 409, 'Online payments are corrected through PayMongo refunds.');
        abort_if($gym->payments()->where('corrects_id', $original->id)->exists(), 409, 'This payment was already corrected.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Say why this payment is being corrected.']);

        if ($original->session_booking_id) {
            $correction = DB::transaction(function () use ($original, $request, $data, $bookingLedger, $gym) {
                $booking = SessionBooking::findOrFail($original->session_booking_id);
                TrainingSession::lockForUpdate()->findOrFail($booking->training_session_id);
                $booking = SessionBooking::lockForUpdate()->findOrFail($booking->id);
                abort_if($gym->payments()->where('corrects_id', $original->id)->exists(), 409, 'This payment was already corrected.');

                return $bookingLedger->recordPayment($booking, -(float) $original->amount, $original->method, $request->user(), [
                    'corrects_id' => $original->id, 'note' => "Corrects {$original->receipt_number}: {$data['reason']}",
                ]);
            });

            return response()->json(['payment' => $this->paymentPayload($correction->load(['user', 'receivedBy']))], 201);
        }

        $membership = $gym->memberships()->with('gym')->findOrFail($original->membership_id);

        $correction = DB::transaction(fn () => $ledger->recordPayment(
            $membership,
            -1 * (float) $original->amount,
            $original->method,
            $request->user(),
            ['corrects_id' => $original->id, 'note' => "Corrects {$original->receipt_number}: {$data['reason']}"],
        ));

        return response()->json(['payment' => $this->paymentPayload($correction->load(['user', 'receivedBy']))], 201);
    }
}
