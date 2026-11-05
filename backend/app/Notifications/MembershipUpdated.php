<?php

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Notifications\Notification;

/** Tells an athlete about a decision or change on their membership (R-33, R-36). */
class MembershipUpdated extends Notification
{
    private const TITLES = [
        'approved' => 'Membership approved',
        'rejected' => 'Membership application not approved',
        'activated' => 'Membership active',
        'payment' => 'Payment received',
        'refund' => 'Refund recorded',
        'correction' => 'Payment corrected',
        'renewed' => 'Membership renewed',
        'frozen' => 'Membership frozen',
        'unfrozen' => 'Membership unfrozen',
        'expired' => 'Membership expired',
        'cancelled' => 'Membership cancelled',
    ];

    public function __construct(
        private Membership $membership,
        private string $type,
        private ?string $note,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $gym = $this->membership->gym->name;
        $plan = $this->membership->plan->name;
        $message = "{$plan} at {$gym}.";

        if ($this->note) {
            $message .= ' '.($this->type === 'rejected' ? 'Reason: ' : '').rtrim($this->note, '.').'.';
        }

        return [
            'title' => self::TITLES[$this->type] ?? 'Membership updated',
            'message' => $message,
            'gym_id' => $this->membership->gym_id,
            'membership_id' => $this->membership->id,
            'status' => $this->membership->status->value,
        ];
    }
}
