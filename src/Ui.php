<?php

namespace GlpiPlugin\Entrasso;

/**
 * Renderers of the admin pages visual kit (public/css/ui.css): same
 * design language as the GLPI Style plugin editor, self-contained.
 * Every text argument is plain text and is escaped here; arguments named
 * $body/$html are already-built HTML.
 */
final class Ui
{
    private const PREFIX = 'ent';

    private static bool $styles_printed = false;

    public static function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Prints the kit stylesheet inline (once per page). Inline on purpose:
     * web servers configured to serve *.css only from glpi/public never
     * hand plugin static files to GLPI, so a <link> could 404.
     */
    public static function styles(): string
    {
        if (self::$styles_printed) {
            return '';
        }
        self::$styles_printed = true;
        $css = (string) @file_get_contents(dirname(__DIR__) . '/public/css/ui.css');
        return '<style>' . str_replace('</', '<\/', $css) . '</style>';
    }

    public static function pageStart(string $icon, string $title, string $subtitle, string $actions = '', string $class = ''): string
    {
        $p = self::PREFIX;
        return self::styles()
            . '<div class="' . $p . '-page ' . self::e($class) . '">'
            . '<header class="' . $p . '-intro"><div class="' . $p . '-intro__title">'
            . '<span class="' . $p . '-intro__icon"><i class="ti ' . self::e($icon) . '"></i></span>'
            . '<div><h1>' . self::e($title) . '</h1>' . ($subtitle !== '' ? '<p>' . self::e($subtitle) . '</p>' : '') . '</div></div>'
            . ($actions !== '' ? '<div class="' . $p . '-intro__actions">' . $actions . '</div>' : '')
            . '</header>';
    }

    public static function pageEnd(): string
    {
        return '</div>';
    }

    public static function help(string $text): string
    {
        if ($text === '') {
            return '';
        }
        return '<span class="' . self::PREFIX . '-help" tabindex="0" title="' . self::e($text) . '" aria-label="' . self::e($text) . '">?</span>';
    }

    /**
     * @param string $aside HTML shown on the right of the header (e.g. a status())
     * @param bool   $collapsible <details> (collapsible) or a plain block
     */
    public static function section(string $icon, string $tone, string $title, string $subtitle, string $body, string $aside = '', bool $collapsible = true, string $id = ''): string
    {
        $p = self::PREFIX;
        $head = '<span class="' . $p . '-section__badge ' . $p . '-tone-' . self::e($tone) . '"><i class="ti ' . self::e($icon) . '"></i></span>'
            . '<span class="' . $p . '-section__head"><strong>' . self::e($title) . '</strong>'
            . ($subtitle !== '' ? '<small>' . self::e($subtitle) . '</small>' : '') . '</span>'
            . ($aside !== '' ? '<span class="' . $p . '-section__aside">' . $aside . '</span>' : '');
        $id_attr = $id !== '' ? ' id="' . self::e($id) . '"' : '';

        if ($collapsible) {
            return '<details class="' . $p . '-section"' . $id_attr . ' open><summary>' . $head
                . '<i class="ti ti-chevron-up ' . $p . '-section__chevron"></i></summary>'
                . '<div class="' . $p . '-section__body">' . $body . '</div></details>';
        }
        return '<section class="' . $p . '-section"' . $id_attr . '><div class="' . $p . '-section__header">' . $head . '</div>'
            . '<div class="' . $p . '-section__body">' . $body . '</div></section>';
    }

    public static function row(array $cards, int $columns = 2, bool $tight = false): string
    {
        $p = self::PREFIX;
        return '<div class="' . $p . '-row ' . $p . '-row--' . $columns . ($tight ? ' ' . $p . '-row--tight' : '') . '">' . implode('', $cards) . '</div>';
    }

    public static function card(string $icon, string $title, string $body, string $help = '', string $class = ''): string
    {
        $p = self::PREFIX;
        return '<div class="' . $p . '-card ' . self::e($class) . '">'
            . ($title !== '' ? '<div class="' . $p . '-card__title">' . ($icon !== '' ? '<i class="ti ' . self::e($icon) . '"></i>' : '') . self::e($title) . self::help($help) . '</div>' : '')
            . $body . '</div>';
    }

    /**
     * Labelled control. $control is the HTML of the input itself (it
     * should carry id="$for").
     */
    public static function control(string $for, string $label, string $control, string $help = '', string $hint = ''): string
    {
        $p = self::PREFIX;
        return '<div class="' . $p . '-control">'
            . ($label !== '' ? '<label class="form-label" for="' . self::e($for) . '">' . self::e($label) . self::help($help) . '</label>' : '')
            . $control
            . ($hint !== '' ? '<div class="form-text">' . self::e($hint) . '</div>' : '')
            . '</div>';
    }

    public static function input(string $name, string $value, array $attrs = []): string
    {
        $html = '<input class="form-control" id="' . self::e($attrs['id'] ?? $name) . '" name="' . self::e($name) . '" value="' . self::e($value) . '"';
        foreach ($attrs + ['type' => 'text'] as $key => $attr) {
            if ($key !== 'id') {
                $html .= ' ' . self::e((string) $key) . '="' . self::e((string) $attr) . '"';
            }
        }
        return $html . '>';
    }

    /** Checkbox switch posting 1 when on (0 when off, through a hidden input) */
    public static function switch(string $name, bool $checked, string $label, string $help = ''): string
    {
        $p = self::PREFIX;
        return '<input type="hidden" name="' . self::e($name) . '" value="0">'
            . '<label class="form-check form-switch ' . $p . '-switch">'
            . '<input class="form-check-input" type="checkbox" name="' . self::e($name) . '" value="1"' . ($checked ? ' checked' : '') . '>'
            . '<span class="form-check-label">' . self::e($label)
            . ($help !== '' ? '<small>' . self::e($help) . '</small>' : '') . '</span></label>';
    }

    /** Value the admin has to paste elsewhere, with a copy button (see copyScript()) */
    public static function copy(string $value, string $id = ''): string
    {
        $p = self::PREFIX;
        $id = $id !== '' ? $id : $p . '-copy-' . substr(md5($value), 0, 8);
        return '<div class="' . $p . '-copy"><code id="' . self::e($id) . '">' . self::e($value) . '</code>'
            . '<button type="button" class="btn btn-sm btn-ghost-secondary" data-' . $p . '-copy="' . self::e($id) . '" title="Copiar">'
            . '<i class="ti ti-copy"></i> Copiar</button></div>';
    }

    /** @param string $kind ok | warn | error | off | info */
    public static function status(string $kind, string $label): string
    {
        return '<span class="' . self::PREFIX . '-status ' . self::PREFIX . '-status--' . self::e($kind) . '">' . self::e($label) . '</span>';
    }

    /** @param string $kind info | warn | danger | ok */
    public static function callout(string $kind, string $icon, string $html): string
    {
        return '<div class="' . self::PREFIX . '-callout ' . self::PREFIX . '-callout--' . self::e($kind) . '"><i class="ti ' . self::e($icon) . '"></i><div>' . $html . '</div></div>';
    }

    /** @param array<string, string> $pairs label => already-built HTML */
    public static function kv(array $pairs): string
    {
        $html = '<dl class="' . self::PREFIX . '-kv">';
        foreach ($pairs as $label => $value) {
            $html .= '<dt>' . self::e($label) . '</dt><dd>' . $value . '</dd>';
        }
        return $html . '</dl>';
    }

    /**
     * Wizard progress. @param string[] $labels @param int $current 1-based
     */
    public static function stepper(array $labels, int $current): string
    {
        $p = self::PREFIX;
        $html = '<ol class="' . $p . '-stepper">';
        foreach (array_values($labels) as $i => $label) {
            $n = $i + 1;
            $class = $n < $current ? 'is-done' : ($n === $current ? 'is-current' : '');
            $html .= '<li class="' . $class . '"' . ($n === $current ? ' aria-current="step"' : '') . '>'
                . '<span class="' . $p . '-stepper__num">' . ($n < $current ? '<i class="ti ti-check"></i>' : $n) . '</span>'
                . self::e($label) . '</li>';
        }
        return $html . '</ol>';
    }

    public static function empty(string $icon, string $text): string
    {
        return '<div class="' . self::PREFIX . '-empty"><i class="ti ' . self::e($icon) . '"></i>' . self::e($text) . '</div>';
    }

    /** Fixed bar at the bottom of the window; add `ent-has-savebar` to the page class */
    public static function savebar(string $buttons): string
    {
        return '<div class="' . self::PREFIX . '-savebar">' . $buttons . '</div>';
    }

    /** Copy-to-clipboard behavior of copy() */
    public static function copyScript(): string
    {
        $p = self::PREFIX;
        return <<<HTML
<script>
document.addEventListener('click', function (e) {
    const button = e.target.closest('[data-{$p}-copy]');
    if (!button) { return; }
    const source = document.getElementById(button.getAttribute('data-{$p}-copy'));
    if (!source || !navigator.clipboard) { return; }
    const original = button.innerHTML;
    navigator.clipboard.writeText(source.textContent).then(function () {
        button.innerHTML = '<i class="ti ti-check"></i> Copiado!';
        setTimeout(function () { button.innerHTML = original; }, 1500);
    });
});
</script>
HTML;
    }
}
