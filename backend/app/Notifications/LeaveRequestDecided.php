<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;

/** Tells an employee their leave request was approved or rejected (R-25). */
class LeaveRequestDecided extends Notification
{
    public function __construct(
        private Gym $gym,
        private LeaveRequest $leaveRequest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status = $this->leaveRequest->status->value;
        $label = config("hr.leave_types.{$this->leaveRequest->type}.label", $this->leaveRequest->type);
        $when = "{$this->leaveRequest->starts_on->toDateString()} to {$this->leaveRequest->ends_on->toDateString()}";

        return [
            'title' => "Leave request {$status}",
            'message' => "Your {$label} from {$when} was {$status}.",
            'gym_id' => $this->gym->id,
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $status,
        ];
    }
}
