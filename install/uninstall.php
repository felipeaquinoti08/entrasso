<?php

function plugin_entrasso_uninstall_run(): bool
{
    global $DB;

    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_entrasso_useraccounts`");
    $DB->doQuery("DELETE FROM `glpi_configs` WHERE `context` = 'plugin:entrasso'");
    // Cron task of the licensing check-in of versions up to 1.0.x
    $DB->delete('glpi_crontasks', ['itemtype' => 'GlpiPlugin\\Entrasso\\LicenseCheck']);

    return true;
}
