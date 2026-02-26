<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Laravel\Socialite\Facades\Socialite;
use Pterodactyl\Models\UserOAuthProvider;
use Pterodactyl\Exceptions\DisplayException;
class OAuthController extends AbstractLoginController
{
    /**
     * Redirect the user to the OAuth provider's authentication page.
     */
    public function redirect(string $provider)
    {
        $response = Socialite::driver($provider)->redirect();

        // Telegram provider returns a full HTML page with a login widget, not a redirect URL
        if (is_string($response)) {
            return response($response);
        }

        return $response;
    }

    /**
     * Handle the callback from the OAuth provider.
     *
     * @throws DisplayException
     */
    public function callback(string $provider, Request $request)
    {
        \Log::info('OAuth callback reached', ['provider' => $provider, 'params' => $request->all()]);

        try {
            $socialiteUser = Socialite::driver($provider)->user();
            \Log::info('OAuth user resolved', ['id' => $socialiteUser->getId(), 'nickname' => $socialiteUser->getNickname(), 'email' => $socialiteUser->getEmail()]);
        } catch (\Throwable $e) {
            \Log::error('OAuth callback error', ['provider' => $provider, 'error' => $e->getMessage(), 'class' => get_class($e)]);
            return redirect('/auth/login')->withErrors(['error' => 'Authentication failed or was canceled.']);
        }

        $oauthProvider = UserOAuthProvider::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($oauthProvider) {
            \Log::info('OAuth existing user found', ['user_id' => $oauthProvider->user_id]);
            $user = $oauthProvider->user;

            if ($user->use_totp) {
                Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

                $request->session()->put('auth_confirmation_token', [
                    'user_id' => $user->id,
                    'token_value' => $token = Str::random(64),
                    'expires_at' => now()->addMinutes(5),
                ]);

                return redirect('/auth/login/checkpoint')->with('token', $token);
            }

            return $this->sendLoginResponseAndRedirect($user, $request);
        }

        \Log::info('OAuth creating new user');

        $email = $socialiteUser->getEmail() ?: $socialiteUser->getId() . '@' . $provider . '.local';
        $username = $socialiteUser->getNickname() ?: $provider . '_' . $socialiteUser->getId();
        $nameFirst = $socialiteUser->getName() ?: 'User';
        $nameLast = null;

        if (User::where('username', $username)->exists()) {
            $username = $username . '_' . Str::random(4);
        }

        if (User::where('email', $email)->exists()) {
            \Log::warning('OAuth email already exists', ['email' => $email]);
            return redirect('/auth/login')->withErrors(['error' => 'An account with this email already exists.']);
        }

        \DB::beginTransaction();
        try {
            $user = User::create([
                'email' => $email,
                'username' => $username,
                'name_first' => $nameFirst,
                'name_last' => $nameLast,
                'password' => bcrypt(Str::random(32)),
                'uuid' => Str::uuid()->toString(),
            ]);

            UserOAuthProvider::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
            ]);

            \DB::commit();
            \Log::info('OAuth user created', ['user_id' => $user->id, 'email' => $email]);

            return $this->sendLoginResponseAndRedirect($user, $request);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('OAuth user creation failed', ['error' => $e->getMessage()]);
            return redirect('/auth/login')->withErrors(['error' => 'Could not create account: ' . $e->getMessage()]);
        }
    }

    /**
     * Helper to log the user in and redirect to the dashboard.
     */
    protected function sendLoginResponseAndRedirect(User $user, Request $request)
    {
        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();

        $this->clearLoginAttempts($request);
        $this->auth->guard()->login($user, true);

        \Illuminate\Support\Facades\Event::dispatch(new \Pterodactyl\Events\Auth\DirectLogin($user, true));

        return redirect('/');
    }
}
