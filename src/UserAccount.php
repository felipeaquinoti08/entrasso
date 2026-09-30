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

        echo Ui::styles();
        echo '<div class="ent-page m-3">';

        if ($account === null) {
            echo Ui::section('ti-brand-windows', 'blue', self::getTypeName(1), '', Ui::empty(
                'ti-link-off',
                __('Esta conta não está vinculada a nenhuma identidade Microsoft Entra ID.', 'entrasso')
            ), Ui::status('off', __('Não vinculada', 'entrasso')), false);
        } else {
            $fields = $account->fields;
            echo Ui::section('ti-brand-windows', 'blue', self::getTypeName(1), __('Identidade Microsoft Entra ID ligada a este usuário', 'entrasso'), Ui::row([
                Ui::card('ti-id-badge', __('Identidade', 'entrasso'), Ui::kv([
                    __('UPN', 'entrasso')             => Ui::e($fields['entra_upn']),
                    _n('Email', 'Emails', 1)          => $fields['entra_email'] ? Ui::e($fields['entra_email']) : '<span class="text-muted">-</span>',
                    __('Object ID (oid)', 'entrasso') => '<code>' . Ui::e($fields['entra_oid']) . '</code>',
                    __('Tenant', 'entrasso')          => '<code>' . Ui::e($fields['entra_tenant_id']) . '</code>',
                ])),
                Ui::card('ti-clock', __('Histórico', 'entrasso'), Ui::kv([
                    __('Vinculado desde', 'entrasso')            => Ui::e(Html::convDateTime($fields['date_creation'])),
                    __('Último login via Microsoft', 'entrasso') => $fields['last_login'] ? Ui::e(Html::convDateTime($fields['last_login'])) : '<span class="text-muted">-</span>',
                ])),
            ]), Ui::status('ok', __('Vinculada', 'entrasso')), false);
        }

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
