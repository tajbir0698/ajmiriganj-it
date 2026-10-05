<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;

class PurchaseReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return false;
    }
}
