<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice;

use App\Domain\Auth\Actions\CreateUser;
use App\Domain\Auth\Actions\ResetUserPassword;
use App\Domain\Auth\Actions\SetUserActive;
use App\Domain\Auth\Actions\UpdateUser;
use App\Domain\Auth\Exceptions\UserChangeRefused;
use App\Domain\Auth\Models\User;
use App\Http\Presenters\UserPresenter;
use App\Http\Requests\BackOffice\ResetUserPasswordRequest;
use App\Http\Requests\BackOffice\StoreUserRequest;
use App\Http\Requests\BackOffice\UpdateUserRequest;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pengguna: any active user manages every user (ADR-0009). Rules live in App\Domain\Auth\Actions. */
final class UserController
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        /** @var User $viewer */
        $viewer = $request->user();
        $activeCount = User::query()->where('is_active', true)->count();

        $users = User::query()
            ->when($filters['search'] ?? null, static function (Builder $q, string $search): void {
                $term = addcslashes($search, '%_\\');
                $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%"));
            })
            ->when($filters['status'] ?? null, static fn (Builder $q, string $status) => $q->where('is_active', $status === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('users/UserListPage', [
            'filters' => array_merge(['search' => null, 'status' => null], array_intersect_key($filters, ['search' => 1, 'status' => 1])),
            'users' => Paginated::present($users, static fn (User $user): array => UserPresenter::row($user, $viewer, $activeCount)),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $create): RedirectResponse
    {
        /** @var array{name: string, username: string, password: string} $data */
        $data = $request->validated();
        $user = $create($data['name'], $data['username'], $data['password'], $this->actor($request));

        return back()->with('success', "Pengguna {$user->username} ditambahkan.");
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $update): RedirectResponse
    {
        $update($user, (string) $request->validated('name'), $this->actor($request));

        return back()->with('success', 'Nama pengguna diperbarui.');
    }

    public function deactivate(Request $request, User $user, SetUserActive $setActive): RedirectResponse
    {
        return $this->setActive($request, $user, false, $setActive);
    }

    public function activate(Request $request, User $user, SetUserActive $setActive): RedirectResponse
    {
        return $this->setActive($request, $user, true, $setActive);
    }

    public function password(ResetUserPasswordRequest $request, User $user, ResetUserPassword $reset): RedirectResponse
    {
        try {
            $reset($user, (string) $request->validated('password'), $this->actor($request));
        } catch (UserChangeRefused $refused) {
            return back()->with('error', $refused->getMessage());
        }

        return back()->with('success', "Kata sandi {$user->username} diganti. Sampaikan secara pribadi.");
    }

    private function setActive(Request $request, User $user, bool $active, SetUserActive $setActive): RedirectResponse
    {
        try {
            $setActive($user, $active, $this->actor($request));
        } catch (UserChangeRefused $refused) {
            return back()->with('error', $refused->getMessage());
        }

        return back()->with('success', $active ? "{$user->username} diaktifkan." : "{$user->username} dinonaktifkan dan langsung keluar.");
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
