<?php
/**
 * inc/menu.class.php — UXKnowDash v1.5.0
 */
class PluginUxknowdashMenu extends CommonGLPI {

    public static $rightname = 'plugin_uxknowdash_view';

    public static function getMenuName(): string {
        return __('Dashboard', 'uxknowdash');
    }

    public static function getMenuContent(): array {
        if (!Session::haveRight(static::$rightname, READ)) {
            return [];
        }
        return [
            'title' => __('Dashboard', 'uxknowdash'),
            'page'  => '/plugins/uxknowdash/front/index.php',
            'icon'  => 'ti ti-chart-bar',
            'links' => [
                'search' => '/plugins/uxknowdash/front/index.php',
            ],
        ];
    }

    public static function canView(): bool {
        return Session::haveRight('plugin_uxknowdash_view', READ);
    }
}
