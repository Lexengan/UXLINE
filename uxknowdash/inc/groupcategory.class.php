<?php
class PluginUxknowdashGroupCategory extends CommonDBTM {

    public static $rightname = 'plugin_uxknowdash_view';

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        return '';
    }

    public static function getGroupsForLevel(string $level, int $entity): array {
        global $DB;
        $rows = $DB->request([
            'SELECT'  => ['groups_id', 'itilcategories_id'],
            'FROM'    => 'glpi_plugin_uxknowdash_groupcategories',
            'WHERE'   => ['level' => $level, 'entities_id' => $entity],
        ]);
        return iterator_to_array($rows, false);
    }
}