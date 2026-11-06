<?php

declare(strict_types=1);

namespace App\Http\Requests\BackOffice;

use App\Domain\Auth\Username;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Username and password sign-in (ADR-0009). Errors are generic so they do not reveal which usernames exist. */
final class LoginRequest extends FormRequest
{
    /** Per username and address: slows guessing one account. */
    private const int MAX_ATTEMPTS = 5;

    /** Per address: slows trying many usernames from one place. */
    private const int MAX_ATTEMPTS_PER_IP = 20;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /** @throws ValidationException */
    public function authenticate(): void
    {
        $keys = [$this->accountKey() => self::MAX_ATTEMPTS, $this->addressKey() => self::MAX_ATTEMPTS_PER_IP];

        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                event(new Lockout($this));

                throw ValidationException::withMessages([
                    'username' => __('Terlalu banyak percobaan. Coba lagi dalam :seconds detik.', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        $credentials = ['username' => $this->username(), 'password' => (string) $this->string('password'), 'is_active' => true];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            foreach (array_keys($keys) as $key) {
                RateLimiter::hit($key, 60);
            }

            throw ValidationException::withMessages(['username' => __('Username atau kata sandi salah.')]);
        }

        RateLimiter::clear($this->accountKey());
    }

    private function username(): string
    {
        return Username::normalize((string) $this->string('username'));
    }

    private function accountKey(): string
    {
        return 'login:'.$this->username().'|'.$this->ip();
    }

    private function addressKey(): string
    {
        return 'login-ip:'.$this->ip();
    }
}
