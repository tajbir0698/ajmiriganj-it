<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        // Allowed for Super Admin (CRUD) and Manager (quick-add via POS when credit enabled)
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->isSuperAdmin();
    }
}
