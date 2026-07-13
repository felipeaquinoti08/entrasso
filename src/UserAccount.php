<?php

namespace GlpiPlugin\Entrasso;

use CommonDBTM;
use CommonGLPI;
use Html;
use Session;
use User;

/**
 * Links a GLPI user to their Microsoft Entra ID identity. Keyed by
 * (tenant, oid) - Entra's Object ID is stable per user/tenant even if
 * the person's e-mail or display name changes later, unlike matching on
 * e-mail (only ever used once, at first login, to find/create this row).
 *
 * Doubles as the audit trail: shown as a read-only tab on the User form
 * so an admin can see at a glance that an account is SSO-linked, when it
 * was created, and when it last signed in via Microsoft.
 */
class UserAccount extends CommonDBTM
{
    public static $rightname = 'user';

    public static function getTypeName($nb = 0): string
    {
        return __('Conta Microsoft', 'entrasso');
    }

    public static function getIcon(): string
    {
        return 'ti ti-brand-windows';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof User || !Session::haveRight('user', READ)) {
            return '';
        }
        return self::getTypeName(1);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof User) {
            return false;
        }

        $account = self::getForUser((int) $item->getID());

        echo '<div class="card m-3">';
        echo '<div class="card-body">';

        if ($account === null) {
            echo '<p class="text-muted mb-0">' .
                __('Esta conta não está vinculada a nenhuma identidade Microsoft Entra ID.', 'entrasso') .
                '</p>';
        } else {
            echo '<dl class="row mb-0">';
            echo '<dt class="col-3">' . __('Tenant', 'entrasso') . '</dt>';
            echo '<dd class="col-9">' . htmlspecialchars($account->fields['entra_tenant_id']) . '</dd>';
            echo '<dt class="col-3">' . __('Object ID (oid)', 'entrasso') . '</dt>';
            echo '<dd class="col-9">' . htmlspecialchars($account->fields['entra_oid']) . '</dd>';
            echo '<dt class="col-3">' . __('UPN', 'entrasso') . '</dt>';
            echo '<dd class="col-9">' . htmlspecialchars($account->fields['entra_upn']) . '</dd>';
            echo '<dt class="col-3">' . _n('Email', 'Emails', 1) . '</dt>';
            echo '<dd class="col-9">' . htmlspecialchars($account->fields['entra_email'] ?? '') . '</dd>';
            echo '<dt class="col-3">' . __('Vinculado desde', 'entrasso') . '</dt>';
            echo '<dd class="col-9">' . htmlspecialchars(Html::convDateTime($account->fields['date_creation'])) . '</dd>';
            echo '<dt class="col-3">' . __('Último login via Microsoft', 'entrasso') . '</dt>';
            echo '<dd class="col-9">' .
                ($account->fields['last_login'] ? htmlspecialchars(Html::convDateTime($account->fields['last_login'])) : '-') .
                '</dd>';
            echo '</dl>';
        }

        echo '</div>';
        echo '</div>';

        return true;
    }

    public static function getForUser(int $users_id): ?self
    {
        $account = new self();
        return $account->getFromDBByCrit(['users_id' => $users_id]) ? $account : null;
    }

    public static function getForEntraIdentity(string $tenant_id, string $oid): ?self
    {
        $account = new self();
        return $account->getFromDBByCrit([
            'entra_tenant_id' => $tenant_id,
            'entra_oid'       => $oid,
        ]) ? $account : null;
    }
}
