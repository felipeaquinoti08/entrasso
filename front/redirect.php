<?php

use GlpiPlugin\Entrasso\Config;
use GlpiPlugin\Entrasso\OAuthClient;

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
