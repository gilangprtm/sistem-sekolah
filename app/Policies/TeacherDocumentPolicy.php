<?php

namespace App\Policies;

use App\DocumentModule;
use App\Models\TeacherDocument;
use App\Models\TeacherDocumentFolder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeacherDocumentPolicy
{
    public function create(User $user, DocumentModule $module): bool
    {
        return $module->canManage($user);
    }

    public function view(User $user, TeacherDocument|TeacherDocumentFolder $item, DocumentModule $module): Response
    {
        if ($item->module !== $module->value) {
            return Response::denyAsNotFound();
        }

        if ($user->hasRole('Super Admin')) {
            return Response::allow();
        }

        if (! $module->canView($user)) {
            return Response::denyAsNotFound();
        }

        $folder = $item instanceof TeacherDocumentFolder ? $item : $item->folder;
        if ($folder !== null && $folder->module !== $module->value) {
            return Response::denyAsNotFound();
        }
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

    public function delete(User $user, TeacherDocument|TeacherDocumentFolder $item, DocumentModule $module): Response
    {
        $view = $this->view($user, $item, $module);
        if ($view->denied()) {
            return $view;
        }

        return $user->hasRole('Super Admin') || ($module->canManage($user) && $item->created_by === $user->id)
            ? Response::allow() : Response::deny();
    }
}
