<?php

namespace App\Http\Controllers\Gym;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Concerns\PresentsMemberships;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Payment;
use App\Support\MembershipLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** R-34: the payments desk. Entries are never edited; a correction is its own entry. */
class PaymentController extends Controller
{
    use PresentsMemberships;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'membership_id' => ['nullable', 'integer'],
        ]);

        $query = $gym->payments()->with(['user', 'receivedBy']);

        if (! empty($data['membership_id'])) {
            $query->where('membership_id', $data['membership_id']);
        }

        $payments = $query->orderByDesc('paid_at')->orderByDesc('id')->limit(500)->get()
            ->map(fn (Payment $payment) => $this->paymentPayload($payment))
            ->values();

        return response()->json(['payments' => $payments]);
    }

    public function store(Request $request, Gym $gym, MembershipLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'membership_id' => ['required', 'integer', Rule::exists('memberships', 'id')->where('gym_id', $gym->id)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'method' => ['required', Rule::in(array_keys(config('membership.desk_methods')))],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['membership_id.exists' => 'Choose a member of this gym.']);

        $membership = $gym->memberships()->with('gym')->findOrFail($data['membership_id']);

        // R-33: money is taken only after approval, and taking it never approves anything.
        abort_unless(
            in_array($membership->status, [MembershipStatus::AwaitingPayment, MembershipStatus::Active, MembershipStatus::Frozen], true),
            409,
            'Payments can be recorded only for approved memberships.',
        );

        $payment = DB::transaction(fn () => $ledger->recordPayment(
            $membership,
            (float) $data['amount'],
            PaymentMethod::from($data['method']),
            $request->user(),
            ['note' => $data['note'] ?? null],
        ));

        return response()->json(['payment' => $this->paymentPayload($payment->load(['user', 'receivedBy']))], 201);
    }

    /** Reverses a wrong entry in full. The right amount is then recorded as a new payment. */
    public function correct(Request $request, Gym $gym, int $payment, MembershipLedger $ledger): JsonResponse
    {
        $original = $gym->payments()->findOrFail($payment);

        abort_if($original->corrects_id !== null || (float) $original->amount <= 0, 409, 'Only a payment can be corrected, not a correction or refund.');
        abort_if($original->method === PaymentMethod::Paymongo, 409, 'Online payments are corrected through PayMongo refunds.');
        abort_if($gym->payments()->where('corrects_id', $original->id)->exists(), 409, 'This payment was already corrected.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], ['reason.required' => 'Say why this payment is being corrected.']);

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
