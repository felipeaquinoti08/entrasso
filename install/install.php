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

    CronTask::register(
        \GlpiPlugin\Entrasso\LicenseCheck::class,
        'CheckIn',
        10 * MINUTE_TIMESTAMP,
        [
            'comment' => 'Valida a licença do Entrasso junto ao painel de licenciamento.',
            'mode' => CronTask::MODE_EXTERNAL,
        ]
    );

    return true;
}
