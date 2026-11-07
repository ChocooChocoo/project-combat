<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case AllMembers = 'all_members';
    case Session = 'session';
    case CustomerGroup = 'customer_group';
}
