<?php

namespace GlpiPlugin\Entrasso;

/**
 * Renders the "Entrar com Microsoft" link on the login page, via the
 * Glpi\Plugin\Hooks::DISPLAY_LOGIN hook (registered in setup.php).
 *
 * Core only offers one injection point for this hook - a separate
 * side column next to the login form (templates/pages/login.html.twig),
 * not inside the form itself. Since the button should sit right below
 * the "Sign in" button instead, a small script moves our markup there
 * after the page loads rather than requiring a core template override
 * (which would be far more fragile across GLPI updates).
 */
class LoginButton
{
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

        echo '<div id="entrasso-login-wrap" class="d-none">';
        echo '<a class="btn btn-outline-secondary w-100 mb-2" href="' . htmlspecialchars($url) . '">';
        echo '<i class="ti ti-brand-windows"></i> ' . htmlspecialchars($label);
        echo '</a>';
        echo '</div>';

        echo '<script>';
        echo <<<'JS'
            (function () {
                document.addEventListener('DOMContentLoaded', function () {
                    var wrap = document.getElementById('entrasso-login-wrap');
                    var submitBtn = document.querySelector('.form-footer button[name="submit"]');
                    if (wrap && submitBtn) {
                        wrap.classList.remove('d-none');
                        submitBtn.insertAdjacentElement('afterend', wrap);
                    }
                });
            })();
JS;
        echo '</script>';
    }
}
