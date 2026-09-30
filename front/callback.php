<?php

use GlpiPlugin\Entrasso\Config;
use GlpiPlugin\Entrasso\OAuthClient;
use GlpiPlugin\Entrasso\UserProvisioner;

global $CFG_GLPI;

// Session cookie may not be sent back on this cross-site redirect from
// Microsoft if `session.cookie_samesite` is set to `strict` - GLPI's own
// OAuth callback (front/smtp_oauth2_callback.php) works around this with
// a same-origin meta-refresh before touching the session at all.
if (!array_key_exists('cookie_refresh', $_GET)) {
    $url = htmlspecialchars(
        $_SERVER['REQUEST_URI']
        . (str_contains($_SERVER['REQUEST_URI'], '?') ? '&' : '?')
        . 'cookie_refresh'
    );
    echo <<<HTML
<html>
<head><meta http-equiv="refresh" content="0;URL='{$url}'"/></head>
<body></body>
</html>
HTML;
    return;
}

function entrasso_login_error(string $message): never
{
    global $CFG_GLPI;
    Html::nullHeader(__('Entrar com Microsoft', 'entrasso'));
    echo \GlpiPlugin\Entrasso\Ui::styles();
    echo '<div class="ent-page ent-page--narrow" style="max-width:520px;padding-top:12vh">';
    echo '<div class="ent-card" style="text-align:center;justify-items:center;padding:32px 28px">';
    echo '<span class="ent-intro__icon" style="background:color-mix(in srgb,#ef4444 14%,transparent);color:#ef4444"><i class="ti ti-alert-triangle"></i></span>';
    echo '<h2 class="m-0" style="font-size:1.2rem">' . __('Não foi possível entrar com Microsoft', 'entrasso') . '</h2>';
    echo '<p class="text-muted m-0">' . htmlspecialchars($message) . '</p>';
    echo '<a class="btn btn-primary" href="' . htmlspecialchars($CFG_GLPI['root_doc'] . '/') . '"><i class="ti ti-arrow-left"></i> ' . __('Voltar para o login', 'entrasso') . '</a>';
    echo '</div></div>';
    Html::nullFooter();
    exit;
}

if (!Config::isActive()) {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}

if (!empty($_GET['error'])) {
    entrasso_login_error(sprintf(
        __('Autenticação cancelada ou recusada pela Microsoft: %s', 'entrasso'),
        $_GET['error_description'] ?? $_GET['error']
    ));
}

$state_ok = isset($_GET['state'])
    && isset($_SESSION['entrasso_oauth_state'])
    && isset($_SESSION['entrasso_oauth_state_expires'])
    && $_SESSION['entrasso_oauth_state_expires'] >= time()
    && hash_equals((string) $_SESSION['entrasso_oauth_state'], (string) $_GET['state']);

unset($_SESSION['entrasso_oauth_state'], $_SESSION['entrasso_oauth_state_expires']);

if (!$state_ok) {
    entrasso_login_error(__('Não foi possível verificar a autenticidade da resposta da Microsoft. Tente novamente.', 'entrasso'));
}

if (empty($_GET['code'])) {
    entrasso_login_error(__('Código de autorização ausente.', 'entrasso'));
}

try {
    $provider = OAuthClient::provider();
    $token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    $owner = $provider->getResourceOwner($token);
} catch (\Throwable $e) {
    global $PHPLOGGER;
    $PHPLOGGER->error('Entrasso: OAuth token exchange failed: ' . $e->getMessage(), ['exception' => $e]);
    entrasso_login_error(__('Não foi possível concluir a autenticação com a Microsoft.', 'entrasso'));
}

$provisioner = new UserProvisioner();
$result = $provisioner->resolveUser($owner);

if ($result['user'] === null) {
    entrasso_login_error(__('Sua conta Microsoft não está autorizada a acessar este GLPI. Fale com o administrador.', 'entrasso'));
}

$user = $result['user'];
$user->fields['last_login'] = date('Y-m-d H:i:s');

// Best-effort: a Graph hiccup (rate limit, transient network error) must
// never block an otherwise-successful login.
try {
    $provisioner->syncProfileFromGraph($user, $provider, $token);
    $user->getFromDB($user->getID()); // reload after the sync's own update()
} catch (\Throwable $e) {
    global $PHPLOGGER;
    $PHPLOGGER->warning('Entrasso: Graph profile sync failed: ' . $e->getMessage(), ['exception' => $e]);
}

// Auth::$auth_type is private with no public setter - Session::init()
// doesn't actually read it anyway, it uses $user->fields['authtype']
// (already set to Auth::EXTERNAL when the User row was created/matched),
// re-fetched fresh from the DB.
$auth = new Auth();
$auth->user = $user;
$auth->auth_succeded = true;
$auth->extauth = 1;

Session::init($auth);

// Never trust a redirect destination from query string here (open
// redirect risk) - always land on the app root.
Html::redirect($CFG_GLPI['root_doc'] . '/');
