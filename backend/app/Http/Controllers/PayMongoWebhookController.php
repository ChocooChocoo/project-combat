<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Support\MembershipLedger;
use App\Support\PayMongo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * PayMongo tells the backend a checkout was paid (R-35). The message must carry a valid
 * signature, and even then its contents are not trusted: the backend asks PayMongo directly.
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, PayMongo $payMongo, MembershipLedger $ledger): JsonResponse
    {
        if (! $payMongo->validSignature($request->header('Paymongo-Signature'), $request->getContent())) {
            abort(401, 'Invalid signature.');
        }

        if ($request->input('data.attributes.type') !== 'checkout_session.payment.paid') {
            return response()->json(['ignored' => true]);
        }

        $checkoutId = (string) $request->input('data.attributes.data.id');
        $membership = $checkoutId === ''
            ? null
            : Membership::withoutGlobalScope('gym')->where('pending_checkout_id', $checkoutId)->first();

        if (! $membership) {
            return response()->json(['ignored' => true]);
        }

        try {
            $result = $payMongo->checkout($checkoutId);
        } catch (RuntimeException) {
            abort(502, 'Could not confirm with PayMongo.');
        }

        if ($result['paid']) {
            MyMembershipController::recordCheckout($membership, $checkoutId, $result, $ledger);
        }

        return response()->json(['received' => true]);
    }
}
