<?php
class PluginUxknowdashCategoryTarget extends CommonDBTM {

    public static $rightname = 'plugin_uxknowdash_view';

    public static function getTargets(int $entity): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => ['itilcategories_id', 'target_fcr', 'target_kb_usage', 'target_kb_quality'],
            'FROM'   => 'glpi_plugin_uxknowdash_category_targets',
            'WHERE'  => ['entities_id' => $entity],
        ]);
        return iterator_to_array($rows, false);
    }

    public static function saveTarget(int $entity, int $catId, float $fcr, float $kb, float $quality): bool {
        global $DB;
        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_uxknowdash_category_targets',
            'WHERE'  => ['entities_id' => $entity, 'itilcategories_id' => $catId],
            'LIMIT'  => 1,
        ])->current();

        if ($existing) {
            return $DB->update('glpi_plugin_uxknowdash_category_targets',
                ['target_fcr' => $fcr, 'target_kb_usage' => $kb, 'target_kb_quality' => $quality],
                ['id' => $existing['id']]
            );
        }
        return (bool) $DB->insert('glpi_plugin_uxknowdash_category_targets', [
            'entities_id'       => $entity,
            'itilcategories_id' => $catId,
            'target_fcr'        => $fcr,
            'target_kb_usage'   => $kb,
            'target_kb_quality' => $quality,
        ]);
    }
}