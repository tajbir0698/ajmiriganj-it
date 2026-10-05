<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerPayment;
use App\Models\User;

class CustomerPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, CustomerPayment $payment): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, CustomerPayment $payment): bool
    {
        return false;
    }

    public function delete(User $user, CustomerPayment $payment): bool
    {
        return false;
    }
}
