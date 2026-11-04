<?php

namespace App\Policies;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may see and change a gym application and its files (R-14, R-15).
 * Anyone else is told it does not exist (404), so ids cannot be probed.
 */
class GymApplicationPolicy
{
    public function view(User $user, Gym $gym): Response
    {
        return $gym->applicant_id === $user->id || $user->is_platform_admin
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Only the applicant, and only while the application is a draft or returned. */
    public function update(User $user, Gym $gym): Response
    {
        if ($gym->applicant_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $gym->status->editable()
            ? Response::allow()
            : Response::deny('This application has been sent and can no longer be changed.');
    }
}
