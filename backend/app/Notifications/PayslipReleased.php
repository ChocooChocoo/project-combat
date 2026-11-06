<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\PayrollRun;
use Illuminate\Notifications\Notification;

/** R-62: tells an employee their payslip is ready after release. */
class PayslipReleased extends Notification
{
    public function __construct(
        private Gym $gym,
        private PayrollRun $run,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payslip released',
            'message' => 'Your payslip for '.$this->run->starts_on->format('M j').' to '.$this->run->ends_on->format('M j, Y').' is ready.',
            'gym_id' => $this->gym->id,
            'payroll_run_id' => $this->run->id,
        ];
    }
}
