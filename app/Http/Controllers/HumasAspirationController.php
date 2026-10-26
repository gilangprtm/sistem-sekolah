<?php

namespace App\Http\Controllers;

use App\Models\HumasAspiration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class HumasAspirationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', HumasAspiration::class);
        $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $search = $request->string('search')->trim()->toString();
        $aspirations = HumasAspiration::query()
            ->select(['id', 'student_id', 'content', 'created_at'])
            ->with('student:id,full_name,nisn')
            ->when($search !== '', fn ($query) => $query->whereHas('student', function ($query) use ($search): void {
                $query->whereLike('full_name', '%'.$search.'%')->orWhereLike('nisn', '%'.$search.'%');
            }))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (HumasAspiration $aspiration): array => [
                'id' => $aspiration->id,
                'student' => $aspiration->student?->only(['id', 'full_name', 'nisn']),
                'content' => $aspiration->content,
                'created_at' => $aspiration->created_at?->toISOString(),
            ]);

        $response = Inertia::render('humas/silaras/index', [
            'aspirations' => $aspirations,
            'filters' => ['search' => $search],
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
