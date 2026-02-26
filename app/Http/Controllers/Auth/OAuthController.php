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
        } catch (\Throwable $e) {
            return redirect('/auth/login?error=' . urlencode('Authentication failed or was canceled.'));
        }

        $oauthProvider = UserOAuthProvider::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($oauthProvider) {
            $user = $oauthProvider->user;

            // Skip 2FA for OAuth — provider already verified identity
            return $this->sendLoginResponseAndRedirect($user, $request);
        }

        return redirect('/auth/login?error=' . urlencode('No account is linked to this ' . ucfirst($provider) . ' account. Please link it in your account settings first.'));
    }

    /**
     * Redirect to OAuth provider for account linking (logged-in user).
     */
    public function linkRedirect(string $provider)
    {
        $callbackUrl = config('app.url') . '/auth/link/oauth/' . $provider . '/callback';

        $response = Socialite::driver($provider)
            ->redirectUrl($callbackUrl)
            ->redirect();

        if (is_string($response)) {
            return response($response);
        }

        return $response;
    }

    /**
     * Handle callback from OAuth provider for account linking.
     */
    public function linkCallback(string $provider, Request $request)
    {
        $callbackUrl = config('app.url') . '/auth/link/oauth/' . $provider . '/callback';

        try {
            $socialiteUser = Socialite::driver($provider)
                ->redirectUrl($callbackUrl)
                ->user();
        } catch (\Throwable $e) {
            return redirect('/account?error=' . urlencode('Authentication failed or was canceled.'));
        }

        $user = $request->user();

        // Check if this provider account is already linked
        $existing = UserOAuthProvider::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($existing) {
            $msg = $existing->user_id === $user->id
                ? 'This account is already linked.'
                : 'This account is already linked to another user.';
            return redirect('/account?error=' . urlencode($msg));
        }

        // Check if user already has a different account linked for this provider
        $userExisting = UserOAuthProvider::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if ($userExisting) {
            return redirect('/account?error=' . urlencode('You already have a ' . ucfirst($provider) . ' account linked.'));
        }

        UserOAuthProvider::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
        ]);

        Activity::event('user:account.oauth-linked')
            ->property(['provider' => $provider])
            ->log();

        return redirect('/account?success=' . urlencode(ucfirst($provider) . ' linked successfully.'));
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
