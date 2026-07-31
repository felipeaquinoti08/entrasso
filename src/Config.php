<?php

namespace GlpiPlugin\Entrasso;

use Config as GlpiConfig;
use GLPIKey;

/**
 * Typed access to the plugin's settings, persisted as rows in the core
 * `glpi_configs` table (context `plugin:entrasso`) via
 * Config::setConfigurationValues()/getConfigurationValues() - no
 * dedicated table needed for a handful of settings. `client_secret` is
 * encrypted at rest through the SECURED_CONFIGS hook registered in
 * setup.php - that hook only makes setConfigurationValues() encrypt on
 * write, getConfigurationValues() never decrypts automatically on read
 * (confirmed against core's own equivalent use in
 * Glpi\Mail\SMTP\OauthConfig), so it must be decrypted here explicitly.
 */
class Config
{
    public const CONTEXT = 'plugin:entrasso';

    private const FIELDS = [
        'client_id'            => '',
        'client_secret'        => '',
        'tenant_id'             => '',
        'entities_id_default'   => 0,
        'profiles_id_default'   => 0,
        'is_active'              => 0,
        'auto_create'            => 1,
        'match_existing_by_email' => 1,
        'sync_profile_on_login'   => 1,
        'button_label'           => 'Entrar com Microsoft',

        // Licensing check-in (see LicenseCheck::cronCheckIn()). instance_id
        // is generated once on first check-in and reused forever - it's
        // what the license server counts against the contracted quantity,
        // so it must never change for this installation.
        'license_api_url'         => 'https://pc.cciti.com.br',
        'license_key'             => '',
        'license_instance_id'     => '',
        'license_last_status'     => '',
        'license_last_checked_at' => '',
        // Only touched on a successful (valid:true) check-in - see
        // isLicenseFresh(). Kept separate from license_last_checked_at
        // (which records every attempt, successful or not) specifically
        // so staleness can be measured even through a string of
        // unreachable/error attempts.
        'license_last_success_at' => '',
    ];

    // If no successful check-in happens within this window, the license
    // is treated as inactive even if nothing ever explicitly rejected it -
    // covers the case where this GLPI's own cron stops running (crashes,
    // gets disabled...) and would otherwise never check in again, leaving
    // a one-time-licensed install active forever. Enforced live in
    // isActive() (not just in the cron job) so a broken cron can't bypass
    // it by simply never running.
    private const STALE_AFTER_MINUTES = 30;

    public static function get(): array
    {
        $stored = GlpiConfig::getConfigurationValues(self::CONTEXT, array_keys(self::FIELDS));
        if (!empty($stored['client_secret'])) {
            $stored['client_secret'] = (new GLPIKey())->decrypt($stored['client_secret']) ?? '';
        }
        return array_merge(self::FIELDS, $stored);
    }

    public static function set(array $values): void
    {
        $clean = [];
        foreach (self::FIELDS as $key => $default) {
            if (array_key_exists($key, $values)) {
                $clean[$key] = $values[$key];
            }
        }
        GlpiConfig::setConfigurationValues(self::CONTEXT, $clean);
    }

    public static function isActive(): bool
    {
        return (bool) self::get()['is_active'] && self::isLicenseFresh();
    }

    /**
     * True if licensing hasn't been set up at all yet (nothing to be
     * "stale" about - is_active stays 0 until the first successful
     * check-in anyway, see LicenseCheck) or if the last successful
     * check-in was recent enough.
     */
    public static function isLicenseFresh(): bool
    {
        $lastSuccess = self::get()['license_last_success_at'];
        if ($lastSuccess === '') {
            return true;
        }

        $ageInMinutes = (time() - strtotime($lastSuccess)) / 60;
        return $ageInMinutes <= self::STALE_AFTER_MINUTES;
    }
}
