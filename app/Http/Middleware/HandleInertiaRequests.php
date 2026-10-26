<?php

namespace App\Http\Middleware;

use App\DocumentModule;
use App\Models\CounselingNote;
use App\Models\HumasAspiration;
use App\Models\LmsCourse;
use App\Models\StudentViolation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $preferenceKeys = [
            'theme_mode',
            'theme_preset',
            'font',
            'content_layout',
            'navbar_style',
            'sidebar_variant',
            'sidebar_collapsible',
        ];

        $preferences = [];
        foreach ($preferenceKeys as $key) {
            $preferences[$key] = $request->cookie($key);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()?->getAllPermissions()->pluck('name'),
                'roles' => $request->user()?->getRoleNames(),
            ],
            'canAccessStudentViolations' => fn (): bool => $request->user()?->can('viewAny', StudentViolation::class) ?? false,
            'canManageLmsCourses' => fn (): bool => $request->user()?->can('manage', LmsCourse::class) ?? false,
            'canDesignLmsCourses' => fn (): bool => $request->user()?->can('viewAny', LmsCourse::class) ?? false,
            'canAccessLmsMyCourses' => fn (): bool => $request->user()?->can('browseMyCourses', LmsCourse::class) ?? false,
            'canAccessCounseling' => fn (): bool => $request->user()?->can('viewAny', CounselingNote::class) ?? false,
            'canAccessSilaras' => fn (): bool => $request->user()?->can('viewAny', HumasAspiration::class) ?? false,
            'documentNavigation' => fn (): array => $request->user() === null ? [] : collect(DocumentModule::cases())
                ->filter(fn (DocumentModule $module): bool => $module->canView($request->user()))
                ->map(fn (DocumentModule $module): array => [
                    'id' => $module->value.'-documents', 'key' => $module->value, 'group' => $module->label(),
                    'url' => route($module->routeName()),
                ])->values()->all(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'preferences' => $preferences,
        ];
    }
}
