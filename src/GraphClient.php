<?php

namespace GlpiPlugin\Entrasso;

use GuzzleHttp\Exception\BadResponseException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use TheNetworg\OAuth2\Client\Provider\Azure;

/**
 * Thin wrapper around Microsoft Graph's `/me` endpoint, used to fill in
 * profile fields the id_token's claims don't reliably carry (job title,
 * office, phone numbers, photo - `given_name`/`family_name` themselves
 * are also often empty in the token for accounts that never filled them
 * in Entra, even though Graph's own `/me` response has the same gap, so
 * this isn't a fix for missing data - just the richer, canonical source
 * for whatever data does exist).
 */
class GraphClient
{
    private const PROFILE_FIELDS = 'givenName,surname,displayName,mail,userPrincipalName,mobilePhone,businessPhones,jobTitle,officeLocation';

    public function __construct(
        private Azure $provider,
        private AccessTokenInterface $token
    ) {
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getProfile(): ?array
    {
        try {
            $result = $this->provider->get(
                'https://graph.microsoft.com/v1.0/me?$select=' . self::PROFILE_FIELDS,
                $this->token
            );
            return is_array($result) ? $result : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Raw JPEG bytes of the user's M365 profile photo, or null if they
     * don't have one set (a plain 404 from Graph, not an error) or the
     * call otherwise failed.
     *
     * Goes through the same League/Guzzle HTTP client the provider
     * already uses for the token exchange and the /me profile call
     * (both of which are known to work) instead of a raw curl_init() -
     * that plain curl call had no visibility into *why* it was failing
     * (proxy config, CA bundle, timeouts are all handled differently
     * than whatever GLPI/Guzzle already has working) and silently
     * returned null on any failure.
     */
    public function getPhoto(): ?string
    {
        try {
            $request = $this->provider->getAuthenticatedRequest(
                'GET',
                'https://graph.microsoft.com/v1.0/me/photo/$value',
                $this->token
            );
            $response = $this->provider->getResponse($request);
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status !== 404) {
                // 404 just means "no photo set" - anything else is worth
                // knowing about.
                global $PHPLOGGER;
                $PHPLOGGER->warning("Entrasso: Graph photo fetch failed (HTTP {$status}): " . $e->getMessage());
            }
            return null;
        } catch (\Throwable $e) {
            global $PHPLOGGER;
            $PHPLOGGER->warning('Entrasso: Graph photo fetch failed: ' . $e->getMessage());
            return null;
        }

        $body = (string) $response->getBody();
        return $body !== '' ? $body : null;
    }
}
