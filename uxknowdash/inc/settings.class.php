<?php
class PluginUxknowdashSettings extends CommonDBTM {

    public static $rightname = 'plugin_uxknowdash_view';

    public static function get(string $key, $default = null) {
        global $DB;
        $rows = $DB->request([
            'SELECT' => ['value'],
            'FROM'   => 'glpi_plugin_uxknowdash_settings',
            'WHERE'  => ['name' => $key],
            'LIMIT'  => 1,
        ]);
        $row = $rows->current();
        return $row ? $row['value'] : $default;
    }

    public static function set(string $key, $value): void {
        global $DB;
        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_uxknowdash_settings',
            'WHERE'  => ['name' => $key],
            'LIMIT'  => 1,
        ])->current();

        if ($existing) {
            $DB->update('glpi_plugin_uxknowdash_settings',
                ['value' => $value],
                ['name'  => $key]
            );
        } else {
            $DB->insert('glpi_plugin_uxknowdash_settings', [
                'name'  => $key,
                'value' => $value,
            ]);
        }
    }

    public static function canConfig(): bool {
        return Session::haveRight('config', UPDATE);
    }
}