<?php

namespace Pterodactyl\Http\ViewComposers;

use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Helpers\AssetHashService;
use Pterodactyl\Services\Captcha\CaptchaManager;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class AssetComposer
{
  protected CaptchaManager $captcha;
  protected SettingsRepositoryInterface $settings;

  public function __construct(CaptchaManager $captcha, SettingsRepositoryInterface $settings)
  {
    $this->captcha = $captcha;
    $this->settings = $settings;
  }

  /**
   * Provide access to the asset service in the views.
   */
  public function compose(View $view): void
  {
    $view->with('siteConfiguration', [
      'name' => config('app.name') ?? 'Pyrodactyl',
      'locale' => config('app.locale') ?? 'en',
      'timezone' => config('app.timezone') ?? '',
      'captcha' => [
        'enabled' => $this->captcha->getDefaultDriver() !== 'none',
        'provider' => $this->captcha->getDefaultDriver(),
        'siteKey' => $this->getSiteKeyForCurrentProvider(),
        'scriptIncludes' => $this->captcha->getScriptIncludes(),
      ],
      'oauth' => $this->getEnabledOAuthProviders(),
    ]);
  }

  /**
   * Build list of enabled OAuth providers with their UI config.
   */
  private function getEnabledOAuthProviders(): array
  {
    $providers = [];

    foreach (config('oauth.providers', []) as $key => $provider) {
      $serviceConfig = config("services.{$key}", []);

      // Provider is enabled only if client_secret is configured
      if (empty($serviceConfig['client_secret'])) {
        continue;
      }

      $entry = [
        'key' => $key,
        'label' => $provider['label'] ?? ucfirst($key),
        'color' => $provider['color'] ?? '#333',
        'hoverColor' => $provider['hover_color'] ?? '#222',
        'icon' => $provider['icon'] ?? $key,
        'type' => $provider['type'] ?? 'redirect',
      ];

      // For Telegram type, extract numeric bot ID from token
      if ($entry['type'] === 'telegram') {
        $entry['botId'] = explode(':', $serviceConfig['client_secret'], 2)[0] ?: '';
      }

      $providers[] = $entry;
    }

    return $providers;
  }

  /**
   * Get the site key for the currently active captcha provider.
   */
  private function getSiteKeyForCurrentProvider(): string
  {
    $provider = $this->captcha->getDefaultDriver();

    if ($provider === 'none') {
      return '';
    }

    try {
      $driver = $this->captcha->driver();
      if (method_exists($driver, 'getSiteKey')) {
        return $driver->getSiteKey();
      }
    } catch (\Exception $e) {
      // Silently fail to avoid exposing errors to frontend
    }

    return '';
  }
}
