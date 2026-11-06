<?php

namespace App\Notifications;

use App\Models\Gym;
use App\Models\PayrollRun;
use Illuminate\Notifications\Notification;

/** R-61: tells the preparer what happened to their payroll run. */
class PayrollDecided extends Notification
{
    public function __construct(
        private Gym $gym,
        private PayrollRun $run,
        private string $outcome,
        private ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $period = $this->run->starts_on->format('M j').' to '.$this->run->ends_on->format('M j, Y');
        $message = $this->outcome === 'released'
            ? "Payroll for {$period} was released."
            : "Payroll for {$period} was returned for changes.";

        if ($this->outcome === 'rejected' && filled($this->reason)) {
            $message .= ' Reason: '.$this->reason;
        }

        return [
            'title' => $this->outcome === 'released' ? 'Payroll released' : 'Payroll returned',
            'message' => $message,
            'gym_id' => $this->gym->id,
            'payroll_run_id' => $this->run->id,
        ];
    }
}
