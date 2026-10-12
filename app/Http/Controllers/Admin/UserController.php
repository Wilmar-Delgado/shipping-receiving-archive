<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('admin/Users', ['users' => User::orderBy('name')->orderBy('id')->paginate(10)->withQueryString()->through(fn (User $user) => $user->only('id', 'name', 'email', 'role', 'is_active'))]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(12)],
            'role' => ['required', 'in:admin,staff'],
        ]);
        User::create([...$validated, 'is_active' => true]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $user, $validated) {
            // Serialize administrator changes to protect the last active admin.
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            abort_unless($request->user()->fresh()->is_active, 403);
            $user->refresh();
            abort_if(! $validated['is_active'] && $user->role === 'admin' && $user->is_active && $admins->where('is_active', true)->count() <= 1, 422, 'Keep at least one active administrator.');
            $user->update($validated);
        });

        return back()->with('success', 'Account status updated.');
    }
}
