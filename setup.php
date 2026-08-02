<?php

use Glpi\Http\Firewall;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Entrasso\LoginButton;
use GlpiPlugin\Entrasso\UserAccount;

define('PLUGIN_ENTRASSO_VERSION', '1.0.0');
define('PLUGIN_ENTRASSO_MIN_GLPI_VERSION', '11.0.0');
define('PLUGIN_ENTRASSO_MAX_GLPI_VERSION', '11.9.99');

function plugin_version_entrasso(): array
{
    return [
        'name'         => 'Entrasso',
        'version'      => PLUGIN_ENTRASSO_VERSION,
        'author'       => 'Felipe Aquino',
        'license'      => 'Proprietary',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ENTRASSO_MIN_GLPI_VERSION,
                'max' => PLUGIN_ENTRASSO_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

function plugin_init_entrasso(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['entrasso'] = true;

    // client_secret is stored via Config::setConfigurationValues() under
    // context 'plugin:entrasso' - this hook makes GLPI's own GLPIKey
    // service encrypt/decrypt that field transparently, same mechanism
    // core uses for its own SMTP OAuth secrets.
    $PLUGIN_HOOKS[Hooks::SECURED_CONFIGS]['entrasso'] = ['client_secret'];

    // Adds the gear/"Configure" icon next to the plugin in Setup > Plugins.
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['entrasso'] = 'front/config.php';

    // Injects the "Entrar com Microsoft" button into the login page's
    // right-hand panel (templates/pages/login.html.twig) - no template
    // override needed.
    $PLUGIN_HOOKS[Hooks::DISPLAY_LOGIN]['entrasso'] = [LoginButton::class, 'display'];

    // Read-only audit tab on User showing the linked Entra ID identity.
    Plugin::registerClass(UserAccount::class, ['addtabon' => ['User']]);

    // redirect.php and callback.php are hit by a visitor who is not
    // logged in yet - legacy plugin front scripts default to requiring
    // an authenticated session, so both need to be explicitly exempted.
    Firewall::addPluginStrategyForLegacyScripts('entrasso', '#^/front/redirect\.php#', Firewall::STRATEGY_NO_CHECK);
    Firewall::addPluginStrategyForLegacyScripts('entrasso', '#^/front/callback\.php#', Firewall::STRATEGY_NO_CHECK);
}

function plugin_entrasso_install(): bool
{
    include_once(__DIR__ . '/install/install.php');
    return plugin_entrasso_install_run();
}

function plugin_entrasso_uninstall(): bool
{
    include_once(__DIR__ . '/install/uninstall.php');
    return plugin_entrasso_uninstall_run();
}
