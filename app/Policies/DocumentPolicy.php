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
        return $this->viewAny($user) && $document->status !== 'discarded' && ! $document->trashed();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->view($user, $document) && in_array($document->status, ['inbox', 'filed'], true);
    }

    public function discard(User $user, Document $document): bool
    {
        return $this->view($user, $document) && $document->status === 'inbox';
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function file(User $user, Document $document): bool
    {
        return $this->view($user, $document) && $document->status === 'inbox';
    }
}
