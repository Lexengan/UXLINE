<?php
/**
 * inc/menukblist.class.php — UXKnowDash v1.5.0
 * Deuxième entrée de menu pour la Liste KB.
 * GLPI 11 : chaque entrée visible dans le menu doit être une classe distincte
 * enregistrée via $PLUGIN_HOOKS['menu_toadd'].
 */
class PluginUxknowdashMenuKblist extends CommonGLPI {

    public static function getMenuName(): string {
        return __('KB List', 'uxknowdash') . ' (UXKnowDash)';
    }

    public static function getMenuContent(): array {
        if (!Session::haveRight('plugin_uxknowdash_view', READ)) {
            return [];
        }
        return [
            'title' => __('KB List', 'uxknowdash'),
            'page'  => '/plugins/uxknowdash/front/kblist.php',
            'icon'  => 'ti ti-books',
            'links' => [
                'search' => '/plugins/uxknowdash/front/kblist.php',
            ],
        ];
    }

    public static function canView(): bool {
        return Session::haveRight('plugin_uxknowdash_view', READ);
    }
}
