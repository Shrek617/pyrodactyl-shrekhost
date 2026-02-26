<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\UserOAuthProvider;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends ClientApiController
{
    /**
     * Get all linked OAuth providers for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $linked = UserOAuthProvider::where('user_id', $request->user()->id)
            ->get(['provider', 'provider_id', 'created_at'])
            ->keyBy('provider');

        return new JsonResponse(['data' => $linked]);
    }

    /**
     * Link an OAuth provider to the authenticated user's account.
     * Accepts provider_id and hash data from the Telegram widget callback (or similar).
     */
    public function link(string $provider, Request $request): JsonResponse
    {
        $user = $request->user();

        // Validate the OAuth data using Socialite
        try {
            // Merge the POST data into the request query so Socialite can read it
            $request->query->add($request->all());
            $socialiteUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            \Log::error('OAuth link error', ['provider' => $provider, 'error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Invalid authentication data.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if this provider account is already linked to another user
        $existing = UserOAuthProvider::where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if ($existing) {
            if ($existing->user_id === $user->id) {
                return new JsonResponse(['error' => 'This account is already linked.'], Response::HTTP_CONFLICT);
            }
            return new JsonResponse(['error' => 'This account is already linked to another user.'], Response::HTTP_CONFLICT);
        }

        // Check if user already has a different account linked for this provider
        $userExisting = UserOAuthProvider::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if ($userExisting) {
            return new JsonResponse(['error' => 'You already have a ' . ucfirst($provider) . ' account linked.'], Response::HTTP_CONFLICT);
        }

        UserOAuthProvider::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialiteUser->getId(),
        ]);

        Activity::event('user:account.oauth-linked')
            ->property(['provider' => $provider])
            ->log();

        return new JsonResponse(['success' => true], Response::HTTP_CREATED);
    }

    /**
     * Unlink an OAuth provider from the authenticated user's account.
     */
    public function unlink(string $provider, Request $request): JsonResponse
    {
        $deleted = UserOAuthProvider::where('user_id', $request->user()->id)
            ->where('provider', $provider)
            ->delete();

        if (!$deleted) {
            return new JsonResponse(['error' => 'Provider not linked.'], Response::HTTP_NOT_FOUND);
        }

        Activity::event('user:account.oauth-unlinked')
            ->property(['provider' => $provider])
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
