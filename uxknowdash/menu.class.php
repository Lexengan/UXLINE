<?php
/**
 * inc/menu.class.php — UXKnowDash v1.5.9
 *
 * CORRECTION : utiliser 'links' (pas 'options') pour les sous-entrées du menu latéral.
 * 'links' génère les entrées visibles dans la sidebar GLPI.
 * 'options' génère des sous-pages de configuration, invisibles dans la sidebar.
 * Source : impactauto/src/Menu.php — utilise 'links' pour ses sous-entrées.
 *
 * extends CommonDBTM (pas CommonGLPI) pour hériter de getRights() —
 * requis par displayRightsChoiceMatrix avec 'itemtype' => PluginUxknowdashMenu.
 * $notable = true : pas de table SQL associée.
 */
class PluginUxknowdashMenu extends CommonDBTM
{
    public static $rightname = 'plugin_uxknowdash_view';
    protected static $notable = true;

    public static function getTypeName($nb = 0): string
    {
        return __('UXKnowDash', 'uxknowdash');
    }

    public static function getMenuName(): string
    {
        return __('UXKnowDash', 'uxknowdash');
    }

    public static function getMenuContent(): array
    {
        if (!self::canView()) {
            return [];
        }

        $pluginDir = Plugin::getWebDir('uxknowdash');

        return [
            'title' => self::getMenuName(),
            'page'  => $pluginDir . '/front/index.php',
            'icon'  => 'ti ti-chart-bar',
            'links' => [
                __('Dashboard', 'uxknowdash') => $pluginDir . '/front/index.php',
                __('KB List', 'uxknowdash')   => $pluginDir . '/front/kblist.php',
            ],
        ];
    }

    public static function canView(): bool
    {
        return Session::haveRight(static::$rightname, READ);
    }
}
