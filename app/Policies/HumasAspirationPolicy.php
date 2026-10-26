<?php

namespace App\Policies;

use App\Models\User;

class HumasAspirationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin', 'web')
            || ($user->hasRole('Humas', 'web') && $user->can('humas.aspiration.view'));
    }
}
