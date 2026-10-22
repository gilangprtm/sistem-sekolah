<?php

namespace App\Policies;

use App\Models\TeacherDocument;
use App\Models\TeacherDocumentFolder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeacherDocumentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->can('teacher.document.manage');
    }

    public function view(User $user, TeacherDocument|TeacherDocumentFolder $item): Response
    {
        if ($user->hasRole('Super Admin')) {
            return Response::allow();
        }

        $folder = $item instanceof TeacherDocumentFolder ? $item : $item->folder;
        if ($folder !== null) {
            $lineage = $folder->lineage();
            $rootOwner = $lineage[0]->created_by;
            foreach ($lineage as $ancestor) {
                if ($ancestor->visibility === 'private' && $rootOwner !== $user->id) {
                    return Response::denyAsNotFound();
                }
            }

            return Response::allow();
        }

        return $item->visibility === 'public' || $item->created_by === $user->id
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, TeacherDocument|TeacherDocumentFolder $item): Response
    {
        $view = $this->view($user, $item);
        if ($view->denied()) {
            return $view;
        }

        return $user->hasRole('Super Admin') || ($this->create($user) && $item->created_by === $user->id)
            ? Response::allow() : Response::deny();
    }
}
