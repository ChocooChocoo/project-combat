<?php

namespace App\Notifications;

use App\Enums\OvertimeStatus;
use App\Models\Gym;
use App\Models\OvertimeRequest;
use Illuminate\Notifications\Notification;

/** Tells the employee what happened to their overtime request (R-57). */
class OvertimeDecided extends Notification
{
    public function __construct(
        private Gym $gym,
        private OvertimeRequest $request,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->request->status === OvertimeStatus::Approved;
        $message = $approved
            ? "Your overtime on {$this->request->work_date->toDateString()} was approved for {$this->request->approved_minutes} minutes."
            : "Your overtime on {$this->request->work_date->toDateString()} was rejected.";

        return [
            'title' => $approved ? 'Overtime approved' : 'Overtime rejected',
            'message' => $message,
            'gym_id' => $this->gym->id,
            'overtime_request_id' => $this->request->id,
        ];
    }
}
