<?php

namespace App\Support;

use App\Models\Gym;

/**
 * The gym the current request works inside (R-09).
 * Set only by the EnsureGymAccess middleware after checking the person belongs to it.
 */
class CurrentGym
{
    private ?Gym $gym = null;

    public function set(Gym $gym): void
    {
        $this->gym = $gym;
    }

    public function get(): ?Gym
    {
        return $this->gym;
    }

    public function id(): ?int
    {
        return $this->gym?->id;
    }
}
