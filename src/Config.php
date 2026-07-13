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
    ];

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
        return (bool) self::get()['is_active'];
    }
}
