<?php

namespace App\Enums;

/** Where a gym's owner application stands (R-14). Only approved gyms are live (R-18). */
enum GymStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Returned = 'returned';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** The applicant may still change the profile and files. */
    public function editable(): bool
    {
        return $this === self::Draft || $this === self::Returned;
    }
}
