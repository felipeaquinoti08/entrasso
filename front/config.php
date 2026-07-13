<?php

use GlpiPlugin\Entrasso\Config;
use GlpiPlugin\Entrasso\OAuthClient;

Session::checkRight('config', UPDATE);

global $CFG_GLPI;

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
    Html::redirect($CFG_GLPI['root_doc'] . '/plugins/entrasso/front/config.php');
}

$config = Config::get();
$redirect_uri = OAuthClient::getRedirectUri();
$app_name = 'GLPI SSO - ' . (parse_url((string) ($CFG_GLPI['url_base'] ?? ''), PHP_URL_HOST) ?: 'GLPI');

Html::header(__('Entrasso', 'entrasso'));

/**
 * Small "copy to clipboard" button next to a value the admin needs to
 * paste into the Azure Portal - avoids retyping (and mistyping) the
 * redirect URI by hand.
 */
function entrasso_copy_field(string $id, string $value): string
{
    $html = '<code id="' . $id . '">' . htmlspecialchars($value) . '</code> ';
    $html .= '<button type="button" class="btn btn-sm btn-outline-secondary entrasso-copy-btn" data-target="' . $id . '">';
    $html .= '<i class="ti ti-copy"></i> ' . __('Copiar', 'entrasso');
    $html .= '</button>';
    return $html;
}

echo '<div class="card m-3">';
echo '<div class="card-body">';
echo '<h3>' . __('Configurar aplicativo no Microsoft 365', 'entrasso') . '</h3>';
echo '<p class="text-muted">' .
    __('Siga estes passos no Portal do Azure, logado como Administrador Global (Global Admin) do tenant:', 'entrasso') .
    '</p>';

echo '<ol>';
echo '<li>' . __('Clique em "Abrir Portal do Azure" abaixo (abre em nova aba) e depois em "Novo registro".', 'entrasso') . '</li>';
echo '<li>' . sprintf(__('Em "Nome", cole: %s', 'entrasso'), entrasso_copy_field('entrasso-app-name', $app_name)) . '</li>';
echo '<li>' . __('Em "Tipos de conta com suporte", selecione "Somente contas neste diretório organizacional" (single-tenant).', 'entrasso') . '</li>';
echo '<li>' . sprintf(
    __('Em "URI de redirecionamento", escolha o tipo "Web" e cole: %s', 'entrasso'),
    entrasso_copy_field('entrasso-redirect-uri', $redirect_uri)
) . '</li>';
echo '<li>' . __('Clique em "Registrar".', 'entrasso') . '</li>';
echo '<li>' . __('Na página de visão geral do aplicativo criado, copie o "Application (client) ID" e o "Directory (tenant) ID" - cole nos campos correspondentes abaixo.', 'entrasso') . '</li>';
echo '<li>' . __('Vá em "Certificados e segredos" → "Novo segredo do cliente", copie o VALOR gerado (não o ID do segredo) e cole no campo "Client secret" abaixo.', 'entrasso') . '</li>';
echo '</ol>';

echo '<a class="btn btn-primary" target="_blank" rel="noopener" ' .
    'href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade">';
echo '<i class="ti ti-external-link"></i> ' . __('Abrir Portal do Azure', 'entrasso');
echo '</a>';
echo '<div class="form-text mt-1">' .
    __('Se o link não abrir a tela certa: portal.azure.com → Microsoft Entra ID → Registros de aplicativo → Novo registro.', 'entrasso') .
    '</div>';

echo '</div>';
echo '</div>';

$copied_label = addslashes(__('Copiado!', 'entrasso'));
echo Html::scriptBlock(<<<JS
    document.querySelectorAll('.entrasso-copy-btn').forEach(function(btn) {
        const original = btn.innerHTML;
        btn.addEventListener('click', function() {
            const text = document.getElementById(btn.dataset.target).textContent;
            navigator.clipboard.writeText(text).then(function() {
                btn.innerHTML = '<i class="ti ti-check"></i> {$copied_label}';
                setTimeout(function() { btn.innerHTML = original; }, 1500);
            });
        });
    });
JS);

echo '<form method="post" action="' . $CFG_GLPI['root_doc'] . '/plugins/entrasso/front/config.php" class="card m-3">';
echo '<div class="card-body">';
echo '<h3>' . __('Login SSO via Microsoft Entra ID', 'entrasso') . '</h3>';
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

if (!str_starts_with($redirect_uri, 'https://')) {
    echo '<div class="alert alert-danger">' .
        __('Atenção: a URL base do GLPI não usa HTTPS. O Azure recusa redirect URIs HTTP fora de localhost.', 'entrasso') .
        '</div>';
}

echo '<table class="table">';

echo '<tr><td>' . __('Ativo', 'entrasso') . '</td><td>';
Dropdown::showYesNo('is_active', $config['is_active']);
echo '</td></tr>';

echo '<tr><td>' . __('Application (client) ID', 'entrasso') . '</td><td>';
echo Html::input('client_id', ['value' => $config['client_id'], 'size' => 50]);
echo '</td></tr>';

echo '<tr><td>' . __('Client secret', 'entrasso') . '</td><td>';
echo '<input type="password" name="client_secret" class="form-control" style="max-width:400px" autocomplete="new-password" placeholder="' .
    ($config['client_secret'] !== '' ? __('•••••••• (mantenha em branco para não alterar)', 'entrasso') : '') . '">';
echo '</td></tr>';

echo '<tr><td>' . __('Directory (tenant) ID', 'entrasso') . '</td><td>';
echo Html::input('tenant_id', ['value' => $config['tenant_id'], 'size' => 50]);
echo '<div class="form-text">' . __('Nunca use "common"/"organizations" - informe o GUID do tenant específico.', 'entrasso') . '</div>';
echo '</td></tr>';

echo '<tr><td>' . __('Texto do botão de login', 'entrasso') . '</td><td>';
echo Html::input('button_label', ['value' => $config['button_label'], 'size' => 50]);
echo '</td></tr>';

echo '</table>';

echo '<h4 class="mt-3">' . __('Criação automática de usuário', 'entrasso') . '</h4>';
echo '<table class="table">';

echo '<tr><td>' . __('Vincular a um usuário já existente pelo e-mail', 'entrasso') . '</td><td>';
Dropdown::showYesNo('match_existing_by_email', $config['match_existing_by_email']);
echo '<div class="form-text">' .
    __('Se o e-mail da conta Microsoft bater com exatamente 1 usuário já cadastrado (local ou LDAP), vincula em vez de duplicar.', 'entrasso') .
    '</div>';
echo '</td></tr>';

echo '<tr><td>' . __('Criar usuário novo se não encontrar nenhum', 'entrasso') . '</td><td>';
Dropdown::showYesNo('auto_create', $config['auto_create']);
echo '</td></tr>';

echo '<tr><td>' . __('Entidade padrão para usuários criados', 'entrasso') . '</td><td>';
Entity::dropdown(['name' => 'entities_id_default', 'value' => $config['entities_id_default']]);
echo '</td></tr>';

echo '<tr><td>' . __('Perfil padrão para usuários criados', 'entrasso') . '</td><td>';
Profile::dropdown(['name' => 'profiles_id_default', 'value' => $config['profiles_id_default']]);
echo '<div class="form-text">' . __('Sugestão: "Self-Service".', 'entrasso') . '</div>';
echo '</td></tr>';

echo '</table>';

echo '<h4 class="mt-3">' . __('Sincronização de perfil (Microsoft Graph)', 'entrasso') . '</h4>';
echo '<table class="table">';

echo '<tr><td>' . __('Preencher automaticamente nome, e-mail, telefone/celular, foto, cargo e localidade', 'entrasso') . '</td><td>';
Dropdown::showYesNo('sync_profile_on_login', $config['sync_profile_on_login']);
echo '<div class="form-text">' .
    __('Atualiza esses campos a cada login com o que estiver no perfil Microsoft 365 da pessoa - inclusive sobrescrevendo edições manuais feitas no GLPI. Cargo e Localidade são criados automaticamente se ainda não existirem (igual à sincronização LDAP). Usa a permissão "User.Read" do Microsoft Graph, já habilitada por padrão em qualquer app registrado.', 'entrasso') .
    '</div>';
echo '</td></tr>';

echo '</table>';

echo '<div class="mt-3">' . Html::submit(_sx('button', 'Save'), ['name' => 'update']) . '</div>';

echo '</div>';
echo '</form>';

Html::footer();
