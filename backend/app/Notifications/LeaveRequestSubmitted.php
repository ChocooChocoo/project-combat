<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;

/** Tells each approver a new leave request is waiting (R-25). */
class LeaveRequestSubmitted extends Notification
{
    public function __construct(
        private Gym $gym,
        private LeaveRequest $leaveRequest,
        private string $employeeName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = config("hr.leave_types.{$this->leaveRequest->type}.label", $this->leaveRequest->type);

        return [
            'title' => 'New leave request',
            'message' => "{$this->employeeName} asked for {$label} from {$this->leaveRequest->starts_on->toDateString()} to {$this->leaveRequest->ends_on->toDateString()}.",
            'gym_id' => $this->gym->id,
            'leave_request_id' => $this->leaveRequest->id,
            'employee_name' => $this->employeeName,
        ];
    }
}
