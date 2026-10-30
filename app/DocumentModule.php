<?php

namespace App;

use App\Models\User;
use Illuminate\Http\Request;

enum DocumentModule: string
{
    case Inventory = 'inventory';
    case Curriculum = 'curriculum';
    case Teacher = 'teacher';
    case StudentAffairs = 'student_affairs';
    case Canteen = 'canteen';
    case Administration = 'administration';
    case Humas = 'humas';
    case Library = 'library';
    case Counseling = 'counseling';

    public function label(): string
    {
        return match ($this) {
            self::Inventory => 'Inventaris',
            self::Curriculum => 'Kurikulum',
            self::Teacher => 'Guru',
            self::StudentAffairs => 'Kesiswaan',
            self::Canteen => 'Kantin',
            self::Administration => 'Administrasi',
            self::Humas => 'Humas',
            self::Library => 'Perpustakaan',
            self::Counseling => 'Konseling',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Inventory => 'inventaris/dokumen',
            self::Curriculum => 'kurikulum/dokumen',
            self::Teacher => 'guru/dokumen',
            self::StudentAffairs => 'kesiswaan/dokumen',
            self::Canteen => 'kantin/dokumen',
            self::Administration => 'administrasi/dokumen',
            self::Humas => 'humas/dokumen',
            self::Library => 'perpustakaan/dokumen',
            self::Counseling => 'konseling/dokumen',
        };
    }

    public function permission(string $action): string
    {
        return $this->value.'.document.'.$action;
    }

    public function routeName(string $action = 'index'): string
    {
        return $this->value.'.documents.'.$action;
    }

    public function canView(User $user): bool
    {
        if ($this === self::Administration) {
            return $user->hasRole('Super Admin');
        }

        return $user->hasRole('Super Admin')
            || $user->can($this->permission('view'))
            || $user->can($this->permission('manage'));
    }

    public function canManage(User $user): bool
    {
        return $this->canView($user) && ($user->hasRole('Super Admin') || $user->can($this->permission('manage')));
    }

    public static function fromRequest(Request $request): self
    {
        $module = self::tryFrom(explode('.', $request->route()?->getName() ?? '')[0]);
        abort_if($module === null, 404);

        return $module;
    }
}
