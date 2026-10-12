<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['admin', 'staff'], true);
    }

    public function view(User $user, Document $document): bool
    {
        return $this->viewAny($user);
    }
}
