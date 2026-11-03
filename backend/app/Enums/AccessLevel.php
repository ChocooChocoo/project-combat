<?php

namespace App\Enums;

/** How far an employee may go inside one module (R-08). Higher includes lower. */
enum AccessLevel: string
{
    case View = 'view';
    case Manage = 'manage';
    case Approve = 'approve';

    public function rank(): int
    {
        return match ($this) {
            self::View => 1,
            self::Manage => 2,
            self::Approve => 3,
        };
    }

    public function covers(self $needed): bool
    {
        return $this->rank() >= $needed->rank();
    }
}
