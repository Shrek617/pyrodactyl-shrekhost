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
            return redirect('/auth/login?error=' . urlencode('Authentication failed or was canceled.'));
        }

        $oauthProvider = UserOAuthProvider::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($oauthProvider) {
            \Log::info('OAuth existing user found', ['user_id' => $oauthProvider->user_id]);
            $user = $oauthProvider->user;

            // Skip 2FA for OAuth — provider already verified identity
            return $this->sendLoginResponseAndRedirect($user, $request);
        }

        \Log::warning('OAuth provider not linked', ['provider' => $provider, 'provider_id' => $socialiteUser->getId()]);
        return redirect('/auth/login?error=' . urlencode('No account is linked to this ' . ucfirst($provider) . ' account. Please link it in your account settings first.'));
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
