<?php

namespace GlpiPlugin\Entrasso;

/**
 * Renders the "Entrar com Microsoft" button on the login page, via the
 * Glpi\Plugin\Hooks::DISPLAY_LOGIN hook (registered in setup.php).
 *
 * Core only offers one injection point for this hook - a separate
 * side column next to the login form (templates/pages/login.html.twig),
 * not inside the form itself. Since the button should sit right below
 * the "Sign in" button instead, a small script moves our markup there
 * after the page loads rather than requiring a core template override
 * (which would be far more fragile across GLPI updates).
 *
 * Exception: with the GLPI Style plugin's login page (body.gs-login), the
 * side column is already laid out under the form, after an "ou continue
 * com" separator - the button then stays where it is.
 *
 * Styled after Microsoft's sign-in button guidelines (four-square logo,
 * neutral button), with a dark variant for dark login themes.
 */
class LoginButton
{
    private const LOGO = '<svg class="entrasso-ms-logo" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 21 21" aria-hidden="true">'
        . '<rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/>'
        . '<rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>';

    public static function display(): void
    {
        if (!Config::isActive()) {
            return;
        }

        $config = Config::get();
        if ($config['client_id'] === '' || $config['tenant_id'] === '') {
            return;
        }

        global $CFG_GLPI;
        $url = $CFG_GLPI['root_doc'] . '/plugins/entrasso/front/redirect.php';
        $label = $config['button_label'] !== '' ? $config['button_label'] : __('Entrar com Microsoft', 'entrasso');

        echo '<style>' . self::CSS . '</style>';

        echo '<div id="entrasso-login-wrap" class="d-none">';
        echo '<a class="btn w-100 mb-2 entrasso-ms-btn" href="' . htmlspecialchars($url) . '">';
        echo self::LOGO . '<span>' . htmlspecialchars($label) . '</span>';
        echo '</a>';
        echo '</div>';

        echo '<script>';
        echo <<<'JS'
            (function () {
                document.addEventListener('DOMContentLoaded', function () {
                    var wrap = document.getElementById('entrasso-login-wrap');
                    if (!wrap) {
                        return;
                    }
                    wrap.classList.remove('d-none');
                    if (document.body.classList.contains('gs-login')) {
                        return;
                    }
                    var submitBtn = document.querySelector('.form-footer button[name="submit"]');
                    if (submitBtn) {
                        submitBtn.insertAdjacentElement('afterend', wrap);
                    }
                });
            })();
JS;
        echo '</script>';
    }

    private const CSS = <<<'CSS'
.entrasso-ms-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:44px;
border:1px solid #8c8c8c;border-radius:var(--gs-radius,var(--tblr-border-radius,4px));background:#fff;color:#5e5e5e;
font-weight:600;transition:background-color .15s ease,border-color .15s ease,box-shadow .15s ease;}
.entrasso-ms-btn:hover,.entrasso-ms-btn:focus-visible{border-color:#5e5e5e;background:#f3f3f3;color:#3b3b3b;
box-shadow:0 6px 16px -10px rgba(0,0,0,.35);}
.entrasso-ms-logo{flex:none;}
[data-glpi-theme-dark="1"] body:not(.gs-form-light) .entrasso-ms-btn,body.gs-form-dark .entrasso-ms-btn{
border-color:#5e5e5e;background:#2f2f2f;color:#fff;}
[data-glpi-theme-dark="1"] body:not(.gs-form-light) .entrasso-ms-btn:hover,body.gs-form-dark .entrasso-ms-btn:hover{background:#3b3b3b;color:#fff;}
CSS;
}
