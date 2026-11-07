<?php

namespace App\Enums;

enum PlatformNoticeAudience: string
{
    case Owners = 'owners';
    case AllUsers = 'all_users';
}
