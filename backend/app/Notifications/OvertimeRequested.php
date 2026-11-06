<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\OvertimeRequest;
use Illuminate\Notifications\Notification;

/** Tells each approver that overtime is waiting (R-57). */
class OvertimeRequested extends Notification
{
    public function __construct(
        private Gym $gym,
        private OvertimeRequest $request,
        private string $employeeName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Overtime request',
            'message' => "{$this->employeeName} asked for {$this->request->requested_minutes} minutes of overtime on {$this->request->work_date->toDateString()}.",
            'gym_id' => $this->gym->id,
            'overtime_request_id' => $this->request->id,
            'employee_name' => $this->employeeName,
        ];
    }
}
