<?php

function plugin_entrasso_install_run(): bool
{
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    $migration = new Migration(PLUGIN_ENTRASSO_VERSION);

    if (!$DB->tableExists('glpi_plugin_entrasso_useraccounts')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_entrasso_useraccounts` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `users_id` int {$default_key_sign} NOT NULL DEFAULT '0',
            `entra_tenant_id` char(36) NOT NULL DEFAULT '',
            `entra_oid` char(36) NOT NULL DEFAULT '',
            `entra_upn` varchar(255) NOT NULL DEFAULT '',
            `entra_email` varchar(255) DEFAULT NULL,
            `last_login` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`entra_tenant_id`,`entra_oid`),
            UNIQUE KEY `users_id` (`users_id`),
            KEY `entra_upn` (`entra_upn`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;");
    }

    $migration->executeMigration();

    plugin_entrasso_remove_licensing();

    return true;
}

/**
 * Up to 1.0.x the plugin required a license: a "CheckIn" cron task
 * validated it every 10 minutes and its state lived in glpi_configs.
 * Entrasso is free since 1.1.0 - drop both on update (the cron task class
 * no longer exists, so leaving it would make the cron fail every run).
 */
function plugin_entrasso_remove_licensing(): void
{
    global $DB;

    $DB->delete('glpi_crontasks', ['itemtype' => 'GlpiPlugin\\Entrasso\\LicenseCheck']);
    $DB->delete('glpi_configs', [
        'context' => \GlpiPlugin\Entrasso\Config::CONTEXT,
        'name'    => \GlpiPlugin\Entrasso\Config::LEGACY_FIELDS,
    ]);
}
