<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\PayrollRun;
use Illuminate\Notifications\Notification;

/** R-61: tells the current step's approvers that a payroll run is waiting. */
class PayrollSubmitted extends Notification
{
    public function __construct(
        private Gym $gym,
        private PayrollRun $run,
        private string $step,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payroll needs your approval',
            'message' => 'Payroll for '.$this->run->starts_on->format('M j').' to '.$this->run->ends_on->format('M j, Y')
                .' is waiting for '.$this->step.'.',
            'gym_id' => $this->gym->id,
            'payroll_run_id' => $this->run->id,
            'step' => $this->step,
        ];
    }
}
