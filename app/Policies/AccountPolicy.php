<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Account $account): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Account $account): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Account $account): bool
    {
        if (! $user->isSuperAdmin()) {
            return false;
        }

        return ! $account->transactions()->exists();
    }
}
