<?php

namespace App\Http\Controllers;

use App\Support\DateRange;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\SessionStatus;
use App\Http\Controllers\Concerns\PresentsBookings;
use App\Models\SessionBooking;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\BookingLedger;
use App\Support\CurrentGym;
use App\Support\PayMongo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** R-48: the athlete's own list, intentionally across gyms. */
class MyBookingController extends Controller
{
    use PresentsBookings;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(DateRange::rules());
        $bookings = SessionBooking::withoutGlobalScope('gym')->where('user_id', $request->user()->id)
            ->with(['gym', 'session' => fn ($q) => $q->withoutGlobalScope('gym')->with([
                'coach', 'room' => fn ($rooms) => $rooms->withoutGlobalScope('gym'),
            ])])->get()
            ->sortBy(fn ($booking) => $booking->session->starts_at)->values();
        $payload = fn ($booking) => [
            ...$this->bookingPayload($booking, $request->user()),
            'session' => $this->sessionSummary($booking->session),
            'gym' => ['id' => $booking->gym_id, 'name' => $booking->gym->name],
        ];

        return response()->json([
            'upcoming' => $bookings->filter(fn ($b) => $b->session->starts_at->gte(now()) && $b->status->isLive())->map($payload)->values(),
            'past' => $bookings->filter(fn ($b) => $b->session->starts_at->lt(now()) || ! $b->status->isLive())
                ->filter(fn ($b) => (empty($data['from']) || $b->session->starts_at->toDateString() >= $data['from'])
                    && (empty($data['to']) || $b->session->starts_at->toDateString() <= $data['to']))
                ->reverse()->map($payload)->values(),
        ]);
    }

    /** R-51: serialize checkout creation with desk payments and session cancellation. */
    public function checkout(Request $request, int $booking, PayMongo $payMongo, BookingLedger $ledger): JsonResponse
    {
        $model = $this->own($request, $booking);
        try {
            $url = DB::transaction(function () use ($model, $payMongo, $ledger) {
                TrainingSession::withoutGlobalScope('gym')->lockForUpdate()->findOrFail($model->training_session_id);
                $model = SessionBooking::withoutGlobalScope('gym')->lockForUpdate()->findOrFail($model->id);
                app(CurrentGym::class)->set($model->gym);
                abort_unless($model->status === BookingStatus::Booked && $model->session->status === SessionStatus::Scheduled, 409, 'Only booked sessions can be paid online.');
                if ($model->pending_checkout_id) {
                    $existing = $payMongo->checkout($model->pending_checkout_id);
                    if ($existing['paid']) {
                        self::recordCheckout($model, $model->pending_checkout_id, $existing, $ledger);
                        $model->refresh();
                    } elseif ($existing['status'] === 'active' && $existing['checkout_url'] && $ledger->balance($model) > 0) {
                        return $existing['checkout_url'];
                    }
                }
                $balance = $ledger->balance($model);
                if ($balance <= 0) {
                    return null;
                }
                $frontend = rtrim(config('app.frontend_url'), '/');
                $checkout = $payMongo->createCheckout(
                    $balance, $model->session->name, "Session add-on at {$model->gym->name}",
                    "{$frontend}/bookings?checkout=done&booking={$model->id}",
                    "{$frontend}/bookings?checkout=cancelled&booking={$model->id}",
                    ['session_booking_id' => $model->id, 'gym_id' => $model->gym_id, 'user_id' => $model->user_id],
                );
                $model->forceFill(['pending_checkout_id' => $checkout['id']])->save();

                return $checkout['checkout_url'];
            });
        } catch (RuntimeException $error) {
            if ($error instanceof HttpExceptionInterface) {
                throw $error;
            }
            abort(502, 'Online payment is not available right now. Try again, or pay at the front desk.');
        }
        if ($url === null) {
            throw ValidationException::withMessages(['balance' => 'Nothing is owed on this booking.']);
        }

        return response()->json(['checkout_url' => $url]);
    }

    public function confirm(Request $request, int $booking, PayMongo $payMongo, BookingLedger $ledger): JsonResponse
    {
        $model = $this->own($request, $booking);
        $paid = false;
        if ($model->pending_checkout_id) {
            try {
                $result = $payMongo->checkout($model->pending_checkout_id);
            } catch (RuntimeException) {
                abort(502, 'Could not reach PayMongo to confirm the payment. Try again in a moment.');
            }
            if ($result['paid']) {
                self::recordCheckout($model, $model->pending_checkout_id, $result, $ledger);
                $paid = true;
            }
        } else {
            $paid = $model->payments()->whereNotNull('paymongo_checkout_id')->exists();
        }

        return response()->json(['paid' => $paid, 'booking' => $this->bookingPayload($this->own($request, $booking), $request->user())]);
    }

    public static function recordCheckout(SessionBooking $booking, string $checkoutId, array $result, BookingLedger $ledger): void
    {
        DB::transaction(function () use ($booking, $checkoutId, $result, $ledger) {
            $session = TrainingSession::withoutGlobalScope('gym')->lockForUpdate()->findOrFail($booking->training_session_id);
            $locked = SessionBooking::withoutGlobalScope('gym')->lockForUpdate()->findOrFail($booking->id);
            app(CurrentGym::class)->set($locked->gym);
            if (! $locked->payments()->where('paymongo_checkout_id', $checkoutId)->exists()) {
                $ledger->recordPayment($locked, (float) $result['amount'], PaymentMethod::Paymongo, null, [
                    'paymongo_checkout_id' => $checkoutId, 'paymongo_payment_id' => $result['payment_id'], 'note' => 'Paid online through PayMongo',
                ]);
                // Q-20 also covers a checkout paid just after the gym cancelled its session.
                if ($session->status === SessionStatus::Cancelled) {
                    $ledger->refund($locked, $locked->cancelled_by ? User::find($locked->cancelled_by) : null);
                }
            }
            if ($locked->pending_checkout_id === $checkoutId) {
                $locked->forceFill(['pending_checkout_id' => null])->save();
            }
        });
    }

    private function own(Request $request, int $id): SessionBooking
    {
        $booking = SessionBooking::withoutGlobalScope('gym')->where('user_id', $request->user()->id)->findOrFail($id);
        app(CurrentGym::class)->set($booking->gym);

        return $booking;
    }
}
