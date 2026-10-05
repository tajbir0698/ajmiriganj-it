<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case SUPER_ADMIN = 'Super Admin';
    case MANAGER = 'Manager';
}
