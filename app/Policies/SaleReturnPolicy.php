<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SaleReturn;
use App\Models\User;

class SaleReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, SaleReturn $saleReturn): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, SaleReturn $saleReturn): bool
    {
        return false;
    }

    public function delete(User $user, SaleReturn $saleReturn): bool
    {
        return false;
    }
}
