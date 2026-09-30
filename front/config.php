<?php

use GlpiPlugin\Entrasso\Config;
use GlpiPlugin\Entrasso\OAuthClient;
use GlpiPlugin\Entrasso\Ui;

Session::checkRight('config', UPDATE);

global $CFG_GLPI;

$self_url = $CFG_GLPI['root_doc'] . '/plugins/entrasso/front/config.php';

if (isset($_POST['update'])) {
    $values = [
        'client_id'               => trim((string) ($_POST['client_id'] ?? '')),
        'tenant_id'                => trim((string) ($_POST['tenant_id'] ?? '')),
        'entities_id_default'      => (int) ($_POST['entities_id_default'] ?? 0),
        'profiles_id_default'      => (int) ($_POST['profiles_id_default'] ?? 0),
        'is_active'                 => (int) ($_POST['is_active'] ?? 0),
        'auto_create'               => (int) ($_POST['auto_create'] ?? 0),
        'match_existing_by_email'    => (int) ($_POST['match_existing_by_email'] ?? 0),
        'sync_profile_on_login'      => (int) ($_POST['sync_profile_on_login'] ?? 0),
        'button_label'               => trim((string) ($_POST['button_label'] ?? '')),
    ];

    // Only overwrite the stored secret if a new one was actually typed -
    // the field is always rendered empty, so an untouched submit must not
    // wipe out the existing configured secret.
    if (trim((string) ($_POST['client_secret'] ?? '')) !== '') {
        $values['client_secret'] = trim((string) $_POST['client_secret']);
    }

    Config::set($values);
    Session::addMessageAfterRedirect(__('Configuração salva com sucesso.', 'entrasso'));
    Html::redirect($self_url);
}

$config = Config::get();
$redirect_uri = OAuthClient::getRedirectUri();
$app_name = 'GLPI SSO - ' . (parse_url((string) ($CFG_GLPI['url_base'] ?? ''), PHP_URL_HOST) ?: 'GLPI');
$is_https = str_starts_with($redirect_uri, 'https://');
$is_configured = Config::isConfigured();
$is_active = (bool) $config['is_active'];

Html::header(__('Entrasso', 'entrasso'), $_SERVER['PHP_SELF'], 'config', 'plugin');

// ---------------------------------------------------------------------------
// Overview
// ---------------------------------------------------------------------------

if ($is_active && $is_configured) {
    $overall = Ui::status('ok', __('Login Microsoft ativo', 'entrasso'));
} elseif ($is_active) {
    $overall = Ui::status('warn', __('Ativo, mas falta configurar', 'entrasso'));
} else {
    $overall = Ui::status('off', __('Login Microsoft desativado', 'entrasso'));
}

echo Ui::pageStart(
    'ti-brand-windows',
    __('Entrasso - Login com Microsoft', 'entrasso'),
    __('Login único (SSO) com Microsoft Entra ID, com criação e sincronização automática de usuários.', 'entrasso'),
    $overall,
    'ent-has-savebar'
);

echo '<form method="post" action="' . Ui::e($self_url) . '">';
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

$status_cards = [
    Ui::card('ti-power', __('Login Microsoft', 'entrasso'), Ui::switch(
        'is_active',
        $is_active,
        __('Mostrar "Entrar com Microsoft" na tela de login', 'entrasso'),
        __('Botão de emergência: desligado, o SSO para na hora, sem desinstalar o plugin.', 'entrasso')
    )),
    Ui::card('ti-app-window', __('Aplicativo no Azure', 'entrasso'), Ui::kv([
        __('Situação', 'entrasso')     => $is_configured
            ? Ui::status('ok', __('Configurado', 'entrasso'))
            : Ui::status('warn', __('Incompleto', 'entrasso')),
        __('Client ID', 'entrasso')    => $config['client_id'] !== '' ? '<code>' . Ui::e($config['client_id']) . '</code>' : '<span class="text-muted">-</span>',
        __('Tenant ID', 'entrasso')    => $config['tenant_id'] !== '' ? '<code>' . Ui::e($config['tenant_id']) . '</code>' : '<span class="text-muted">-</span>',
        __('Client secret', 'entrasso') => $config['client_secret'] !== ''
            ? Ui::status('ok', __('Salvo (criptografado)', 'entrasso'))
            : Ui::status('warn', __('Não informado', 'entrasso')),
    ]), __('Preencha os dados do aplicativo na seção "Credenciais do aplicativo".', 'entrasso')),
    Ui::card('ti-lock', __('Endereço do GLPI', 'entrasso'), $is_https
        ? Ui::callout('ok', 'ti-circle-check', Ui::e(__('O GLPI usa HTTPS, como o Azure exige.', 'entrasso')))
        : Ui::callout('danger', 'ti-alert-triangle', Ui::e(__('A URL base do GLPI não usa HTTPS. O Azure recusa URIs de redirecionamento HTTP fora de localhost: ajuste em Configurar > Geral > URL da aplicação.', 'entrasso')))),
];
echo Ui::section('ti-activity', 'blue', __('Visão geral', 'entrasso'), __('Situação atual do login com Microsoft', 'entrasso'), Ui::row($status_cards, 3), '', false);

// ---------------------------------------------------------------------------
// Azure app registration guide
// ---------------------------------------------------------------------------

$steps = [
    Ui::e(__('Abra o Portal do Azure, logado como Administrador Global do tenant, e clique em "Novo registro".', 'entrasso'))
        . '<div><a class="btn btn-sm btn-primary" target="_blank" rel="noopener" href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade">'
        . '<i class="ti ti-external-link"></i> ' . Ui::e(__('Abrir Portal do Azure', 'entrasso')) . '</a></div>'
        . '<div class="form-text m-0">' . Ui::e(__('Se o link não abrir a tela certa: portal.azure.com > Microsoft Entra ID > Registros de aplicativo > Novo registro.', 'entrasso')) . '</div>',
    Ui::e(__('Em "Nome", cole:', 'entrasso')) . Ui::copy($app_name, 'entrasso-app-name'),
    Ui::e(__('Em "Tipos de conta com suporte", escolha "Somente contas neste diretório organizacional" (single-tenant).', 'entrasso')),
    Ui::e(__('Em "URI de redirecionamento", escolha o tipo "Web" e cole:', 'entrasso')) . Ui::copy($redirect_uri, 'entrasso-redirect-uri'),
    Ui::e(__('Clique em "Registrar". Na visão geral do aplicativo, copie o "Application (client) ID" e o "Directory (tenant) ID" para a seção abaixo.', 'entrasso')),
    Ui::e(__('Em "Certificados e segredos" > "Novo segredo do cliente", copie o VALOR gerado (não o ID do segredo) para o campo "Client secret".', 'entrasso')),
];
$guide = '<ol class="ent-steps">';
foreach ($steps as $step) {
    $guide .= '<li>' . $step . '</li>';
}
$guide .= '</ol>';
echo Ui::section(
    'ti-cloud-cog',
    'orange',
    __('Registrar o aplicativo no Microsoft 365', 'entrasso'),
    __('Passo a passo no Portal do Azure, com os valores prontos para copiar', 'entrasso'),
    Ui::card('', '', $guide, '', 'ent-card--flat'),
    $is_configured ? Ui::status('ok', __('Concluído', 'entrasso')) : ''
);

// ---------------------------------------------------------------------------
// Credentials
// ---------------------------------------------------------------------------

$secret_placeholder = $config['client_secret'] !== '' ? __('•••••••• (deixe em branco para manter o atual)', 'entrasso') : '';
echo Ui::section('ti-key', 'purple', __('Credenciais do aplicativo', 'entrasso'), __('Dados do aplicativo registrado no Azure', 'entrasso'), Ui::row([
    Ui::card('ti-id', __('Identificação', 'entrasso'),
        Ui::control('client_id', __('Application (client) ID', 'entrasso'), Ui::input('client_id', $config['client_id'], ['autocomplete' => 'off', 'spellcheck' => 'false']))
        . Ui::control('tenant_id', __('Directory (tenant) ID', 'entrasso'), Ui::input('tenant_id', $config['tenant_id'], ['autocomplete' => 'off', 'spellcheck' => 'false']), '', __('Informe o GUID do tenant específico. Nunca use "common" ou "organizations".', 'entrasso'))),
    Ui::card('ti-shield-lock', __('Segredo e botão', 'entrasso'),
        Ui::control('client_secret', __('Client secret', 'entrasso'), '<input type="password" class="form-control" id="client_secret" name="client_secret" autocomplete="new-password" placeholder="' . Ui::e($secret_placeholder) . '">', __('Guardado criptografado pelo GLPI. O campo aparece sempre vazio: só digite para trocar.', 'entrasso'))
        . Ui::control('button_label', __('Texto do botão de login', 'entrasso'), Ui::input('button_label', $config['button_label'], ['placeholder' => 'Entrar com Microsoft']))),
]));

// ---------------------------------------------------------------------------
// Users
// ---------------------------------------------------------------------------

echo Ui::section('ti-users', 'teal', __('Usuários', 'entrasso'), __('Como a conta Microsoft é ligada a um usuário do GLPI no primeiro login', 'entrasso'), Ui::row([
    Ui::card('ti-link', __('Vínculo e criação', 'entrasso'),
        Ui::switch('match_existing_by_email', (bool) $config['match_existing_by_email'], __('Vincular a um usuário já existente pelo e-mail', 'entrasso'), __('Se o e-mail da conta Microsoft bater com exatamente 1 usuário cadastrado (local ou LDAP), vincula em vez de duplicar.', 'entrasso'))
        . Ui::switch('auto_create', (bool) $config['auto_create'], __('Criar um usuário novo quando não encontrar nenhum', 'entrasso'))),
    Ui::card('ti-building', __('Padrões dos usuários criados', 'entrasso'),
        Ui::control('dropdown_entities_id_default', __('Entidade', 'entrasso'), Entity::dropdown(['name' => 'entities_id_default', 'value' => $config['entities_id_default'], 'display' => false, 'width' => '100%']))
        . Ui::control('dropdown_profiles_id_default', __('Perfil', 'entrasso'), Profile::dropdown(['name' => 'profiles_id_default', 'value' => $config['profiles_id_default'], 'display' => false, 'width' => '100%']), '', __('Sugestão: "Self-Service".', 'entrasso'))),
]));

// ---------------------------------------------------------------------------
// Profile sync
// ---------------------------------------------------------------------------

echo Ui::section('ti-refresh', 'green', __('Sincronização de perfil', 'entrasso'), __('Dados trazidos do Microsoft Graph a cada login', 'entrasso'), Ui::card('ti-user-check', '',
    Ui::switch('sync_profile_on_login', (bool) $config['sync_profile_on_login'], __('Preencher automaticamente nome, e-mail, telefone/celular, foto, cargo e localidade', 'entrasso'), __('Atualiza esses campos a cada login com o que estiver no perfil Microsoft 365 da pessoa, inclusive sobrescrevendo edições manuais feitas no GLPI.', 'entrasso'))
    . Ui::callout('info', 'ti-info-circle', Ui::e(__('Cargo e Localidade são criados automaticamente se ainda não existirem, como na sincronização LDAP. Usa a permissão "User.Read" do Microsoft Graph, já habilitada por padrão em qualquer aplicativo registrado.', 'entrasso'))),
    '', 'ent-card--flat'));

echo Ui::savebar('<button type="submit" name="update" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> ' . Ui::e(_sx('button', 'Save')) . '</button>');

echo '</form>';
echo Ui::pageEnd();
echo Ui::copyScript();

Html::footer();
