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
        try {
            $socialiteUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            \Log::error('OAuth callback error', ['provider' => $provider, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect('/auth/login')->withErrors(['error' => 'Authentication failed or was canceled.']);
        }

        $oauthProvider = UserOAuthProvider::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($oauthProvider) {
            // Find underlying user
            $user = $oauthProvider->user;

            // Proceed with login logic inherited from AbstractLoginController
            if ($user->use_totp) {
                // Return a view or JSON that handles TOTP if necessary, 
                // but since it's a redirect route, we might need a specific handling.
                // For simplicity, let's redirect to TOTP checkpoint with token.
                Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

                $request->session()->put('auth_confirmation_token', [
                    'user_id' => $user->id,
                    'token_value' => $token = Str::random(64),
                    'expires_at' => now()->addMinutes(5),
                ]);

                // We need to redirect to the SPA checkpoint route which will pick up the session or token
                return redirect('/auth/login/checkpoint')->with('token', $token);
            }

            // Normal login without TOTP
            return $this->sendLoginResponseAndRedirect($user, $request);
        }

        // Option A: Auto-create user
        // We need to map Socialite user to Pterodactyl user fields
        $email = $socialiteUser->getEmail() ?: $socialiteUser->getId() . '@' . $provider . '.local';
        $username = $socialiteUser->getNickname() ?: $provider . '_' . $socialiteUser->getId();
        $nameFirst = $socialiteUser->getName() ?: 'User';
        $nameLast = null;

        // Ensure username is unique
        if (User::where('username', $username)->exists()) {
            $username = $username . '_' . Str::random(4);
        }

        // Ensure email is unique
        if (User::where('email', $email)->exists()) {
            return redirect('/auth/login')->withErrors(['error' => 'An account with this email already exists.']);
        }

        \DB::beginTransaction();
        try {
            $user = User::create([
                'email' => $email,
                'username' => $username,
                'name_first' => $nameFirst,
                'name_last' => $nameLast,
                'password' => bcrypt(Str::random(32)), // Random password, they login via OAuth
                'uuid' => Str::uuid()->toString(),
            ]);

            UserOAuthProvider::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
            ]);

            \DB::commit();

            return $this->sendLoginResponseAndRedirect($user, $request);
        } catch (\Exception $e) {
            \DB::rollBack();
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
