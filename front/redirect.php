<?php

use GlpiPlugin\Entrasso\Config;
use GlpiPlugin\Entrasso\LicenseCheck;
use GlpiPlugin\Entrasso\OAuthClient;

// Re-validate the license synchronously on every login attempt (short
// timeout, best-effort) instead of relying solely on the 10-minute cron
// cycle - a license change on the panel (cancelled, quantity lowered...)
// then takes effect immediately instead of up to 10 minutes later. If the
// panel can't be reached in time this is a no-op: Config::isActive()
// below falls back to the last known state from the cron job.
$licenseConfig = Config::get();
if ($licenseConfig['license_api_url'] !== '' && $licenseConfig['license_key'] !== '') {
    LicenseCheck::checkIn(5);
}

if (!Config::isActive()) {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}

$provider = OAuthClient::provider();

// Single-use, short-lived CSRF-equivalent for the OAuth flow (the
// callback is a GET request, so core's own POST-only CSRF token doesn't
// apply here - this is the standard OAuth 'state' parameter pattern,
// same one GLPI's own SMTP OAuth callback uses).
$state = bin2hex(random_bytes(32));
$_SESSION['entrasso_oauth_state'] = $state;
$_SESSION['entrasso_oauth_state_expires'] = time() + 600;

Html::redirect($provider->getAuthorizationUrl([
    'state'  => $state,
    'prompt' => 'select_account',
]));
