<?php

namespace GlpiPlugin\Entrasso;

use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * Builds the Microsoft Entra ID OAuth2 client from the plugin's config.
 * Reuses `thenetworg/oauth2-azure`, already vendored by GLPI core for its
 * own SMTP-over-OAuth feature (`Glpi\Mail\SMTP\OauthProvider\Azure`) -
 * same library, modeled after that class, but wired for interactive
 * user login instead of a background mail-sending token.
 */
class OAuthClient
{
    public static function getRedirectUri(): string
    {
        global $CFG_GLPI;
        return rtrim($CFG_GLPI['url_base'], '/') . '/plugins/entrasso/front/callback.php';
    }

    public static function provider(): Azure
    {
        $config = Config::get();

        $provider = new Azure([
            'clientId'                => $config['client_id'],
            'clientSecret'            => $config['client_secret'],
            'redirectUri'             => self::getRedirectUri(),
            'defaultEndPointVersion'  => Azure::ENDPOINT_VERSION_2_0,
            // User.Read is the basic "read my own profile" Microsoft
            // Graph permission, added by default to every new Entra app
            // registration - no extra admin consent step needed beyond
            // what's already required for openid/profile/email. Used to
            // fetch richer profile data (phone, job title, photo...)
            // than what fits in the id_token's claims.
            'scopes'                  => ['openid', 'profile', 'email', 'https://graph.microsoft.com/User.Read'],
        ]);

        // Never leave this as the multi-tenant 'common'/'organizations'
        // aliases: a specific tenant GUID is what makes the OpenID
        // discovery document (and the issuer check inside it) actually
        // scoped to this organization.
        $provider->tenant = $config['tenant_id'];

        return $provider;
    }
}
