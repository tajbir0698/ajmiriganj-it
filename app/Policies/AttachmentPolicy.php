<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Attachment;
use App\Models\Transaction;
use App\Models\User;

class AttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function view(User $user, Attachment $attachment): bool
    {
        if ($attachment->attachable_type === Transaction::class || $attachment->attachable_type === 'transaction') {
            return $user->hasRole(RoleName::SUPER_ADMIN->value);
        }

        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->hasRole(RoleName::SUPER_ADMIN->value);
    }
}
