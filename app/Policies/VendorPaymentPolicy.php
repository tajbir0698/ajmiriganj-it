<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Models\VendorPayment;

class VendorPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function view(User $user, VendorPayment $vendorPayment): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function update(User $user, VendorPayment $vendorPayment): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function delete(User $user, VendorPayment $vendorPayment): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }
}
