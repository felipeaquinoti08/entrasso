<?php

function plugin_entrasso_uninstall_run(): bool
{
    global $DB;

    $DB->doQuery("DROP TABLE IF EXISTS `glpi_plugin_entrasso_useraccounts`");
    $DB->doQuery("DELETE FROM `glpi_configs` WHERE `context` = 'plugin:entrasso'");

    return true;
}
