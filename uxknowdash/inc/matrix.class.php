<?php
/**
 * inc/matrix.class.php — UXKnowDash v1.1.0
 */
class PluginUxknowdashMatrix extends CommonDBTM {

    public static $rightname = 'plugin_uxknowdash_view';

    public static function compute(int $entity): array {
        global $DB;
        $rows = $DB->request([
            'SELECT'  => [
                'itilcategories_id',
                new QueryExpression('COUNT(*) AS ' . $DB->quoteName('volume')),
                new QueryExpression('AVG(is_fcr) AS '     . $DB->quoteName('fcr')),
                new QueryExpression('AVG(kb_used) AS '    . $DB->quoteName('kb_usage')),
                new QueryExpression('AVG(kb_quality) AS ' . $DB->quoteName('kb_quality')),
            ],
            'FROM'    => 'vw_uxknowdash_metrics',
            'WHERE'   => ['entities_id' => $entity],
            'GROUPBY' => ['itilcategories_id'],
        ]);
        return iterator_to_array($rows, false);
    }

    // WHERE sans alias — colonnes préfixées uniquement via QueryExpression
    private static function buildConditions(array $f): array {
        $where = [];
        if (isset($f['entity']) && $f['entity'] !== '' && $f['entity'] !== null) {
            $where['vw_uxknowdash_metrics.entities_id'] = intval($f['entity']);
        }
        if (!empty($f['date_from'])) {
            $d = date('Y-m-d', strtotime($f['date_from']));
            $where[] = new QueryExpression("glpi_tickets.date_creation >= '$d 00:00:00'");
        }
        if (!empty($f['date_to'])) {
            $d = date('Y-m-d', strtotime($f['date_to']));
            $where[] = new QueryExpression("glpi_tickets.date_creation <= '$d 23:59:59'");
        }
        if (!empty($f['category']) && intval($f['category']) > 0) {
            $where['vw_uxknowdash_metrics.itilcategories_id'] = intval($f['category']);
        }
        if (!empty($f['group']) && intval($f['group']) > 0) {
            $g = intval($f['group']);
            $where[] = new QueryExpression(
                "EXISTS (SELECT 1 FROM glpi_groups_tickets gt WHERE gt.tickets_id = glpi_tickets.id AND gt.groups_id = $g AND gt.type = 2)"
            );
        }
        return $where;
    }

    public static function getKpis(array $f): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression('COUNT(*) AS '                   . $DB->quoteName('total')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.is_fcr)*100,1) AS '  . $DB->quoteName('fcr_pct')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.kb_used)*100,1) AS ' . $DB->quoteName('kb_usage_pct')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.kb_quality),2) AS '  . $DB->quoteName('kb_quality_avg')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => ['glpi_tickets' => ['FKEY' => ['glpi_tickets' => 'id', 'vw_uxknowdash_metrics' => 'id']]],
            'WHERE'     => self::buildConditions($f),
        ]);
        $row = $rows->current();
        return $row ?: ['total' => 0, 'fcr_pct' => 0, 'kb_usage_pct' => 0, 'kb_quality_avg' => 0];
    }

    public static function getVolumeByDay(array $f): array {
        global $DB;
        if (empty($f['date_from'])) $f['date_from'] = date('Y-m-d', strtotime('-30 days'));
        if (empty($f['date_to']))   $f['date_to']   = date('Y-m-d');
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression('DATE(glpi_tickets.date_creation) AS ' . $DB->quoteName('day')),
                new QueryExpression('COUNT(*) AS ' . $DB->quoteName('volume')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => ['glpi_tickets' => ['FKEY' => ['glpi_tickets' => 'id', 'vw_uxknowdash_metrics' => 'id']]],
            'WHERE'     => self::buildConditions($f),
            'GROUPBY'   => [new QueryExpression('DATE(glpi_tickets.date_creation)')],
            'ORDERBY'   => [new QueryExpression('DATE(glpi_tickets.date_creation) ASC')],
        ]);
        return iterator_to_array($rows, false);
    }

    public static function getFcrByCategory(array $f): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression('COALESCE(glpi_itilcategories.completename, CONCAT(\'Cat #\', vw_uxknowdash_metrics.itilcategories_id)) AS ' . $DB->quoteName('cat_name')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.is_fcr)*100,1) AS ' . $DB->quoteName('fcr_pct')),
                new QueryExpression('COUNT(*) AS ' . $DB->quoteName('volume')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => [
                'glpi_tickets'        => ['FKEY' => ['glpi_tickets'        => 'id', 'vw_uxknowdash_metrics' => 'id']],
                'glpi_itilcategories' => ['FKEY' => ['glpi_itilcategories' => 'id', 'vw_uxknowdash_metrics' => 'itilcategories_id']],
            ],
            'WHERE'   => self::buildConditions($f),
            'GROUPBY' => ['vw_uxknowdash_metrics.itilcategories_id'],
            'ORDERBY' => [new QueryExpression('COUNT(*) DESC')],
            'LIMIT'   => 15,
        ]);
        return iterator_to_array($rows, false);
    }

    public static function getKbUsageByCategory(array $f): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression('COALESCE(glpi_itilcategories.completename, CONCAT(\'Cat #\', vw_uxknowdash_metrics.itilcategories_id)) AS ' . $DB->quoteName('cat_name')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.kb_used)*100,1) AS ' . $DB->quoteName('kb_pct')),
                new QueryExpression('COUNT(*) AS ' . $DB->quoteName('volume')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => [
                'glpi_tickets'        => ['FKEY' => ['glpi_tickets'        => 'id', 'vw_uxknowdash_metrics' => 'id']],
                'glpi_itilcategories' => ['FKEY' => ['glpi_itilcategories' => 'id', 'vw_uxknowdash_metrics' => 'itilcategories_id']],
            ],
            'WHERE'   => self::buildConditions($f),
            'GROUPBY' => ['vw_uxknowdash_metrics.itilcategories_id'],
            'ORDERBY' => [new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.kb_used)*100,1) DESC')],
            'LIMIT'   => 15,
        ]);
        return iterator_to_array($rows, false);
    }

    public static function getStatusPie(array $f): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression('SUM(vw_uxknowdash_metrics.is_fcr) AS '                               . $DB->quoteName('fcr')),
                new QueryExpression('SUM(1 - vw_uxknowdash_metrics.is_fcr) AS '                          . $DB->quoteName('no_fcr')),
                new QueryExpression('SUM(CASE WHEN vw_uxknowdash_metrics.kb_used=1 THEN 1 ELSE 0 END) AS '. $DB->quoteName('with_kb')),
                new QueryExpression('SUM(CASE WHEN vw_uxknowdash_metrics.kb_used=0 THEN 1 ELSE 0 END) AS '. $DB->quoteName('without_kb')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => ['glpi_tickets' => ['FKEY' => ['glpi_tickets' => 'id', 'vw_uxknowdash_metrics' => 'id']]],
            'WHERE'     => self::buildConditions($f),
        ]);
        $row = $rows->current();
        return $row ?: ['fcr' => 0, 'no_fcr' => 0, 'with_kb' => 0, 'without_kb' => 0];
    }

    public static function getKbQualityDistrib(array $f): array {
        global $DB;
        $caseExpr = "CASE
            WHEN vw_uxknowdash_metrics.kb_quality = 0  THEN 'Non noté'
            WHEN vw_uxknowdash_metrics.kb_quality <= 1 THEN '★ (0-1)'
            WHEN vw_uxknowdash_metrics.kb_quality <= 2 THEN '★★ (1-2)'
            WHEN vw_uxknowdash_metrics.kb_quality <= 3 THEN '★★★ (2-3)'
            WHEN vw_uxknowdash_metrics.kb_quality <= 4 THEN '★★★★ (3-4)'
            ELSE '★★★★★ (4-5)' END";
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression("$caseExpr AS " . $DB->quoteName('label')),
                new QueryExpression('COUNT(*) AS '  . $DB->quoteName('count')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => ['glpi_tickets' => ['FKEY' => ['glpi_tickets' => 'id', 'vw_uxknowdash_metrics' => 'id']]],
            'WHERE'     => self::buildConditions($f),
            'GROUPBY'   => [new QueryExpression($caseExpr)],
            'ORDERBY'   => [new QueryExpression('MIN(vw_uxknowdash_metrics.kb_quality) ASC')],
        ]);
        return iterator_to_array($rows, false);
    }

    public static function getVolumeByGroup(array $f): array {
        global $DB;
        $conditions = self::buildConditions($f);
        $conditions[] = new QueryExpression('(glpi_groups_tickets.type = 2 OR glpi_groups_tickets.type IS NULL)');
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression('COALESCE(glpi_groups.name, \'Sans groupe\') AS ' . $DB->quoteName('group_name')),
                new QueryExpression('COUNT(*) AS '                                    . $DB->quoteName('volume')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.is_fcr)*100,1) AS ' . $DB->quoteName('fcr_pct')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => [
                'glpi_tickets'        => ['FKEY' => ['glpi_tickets'        => 'id',         'vw_uxknowdash_metrics' => 'id']],
                'glpi_groups_tickets' => ['FKEY' => ['glpi_groups_tickets' => 'tickets_id', 'glpi_tickets'          => 'id']],
                'glpi_groups'         => ['FKEY' => ['glpi_groups'         => 'id',         'glpi_groups_tickets'   => 'groups_id']],
            ],
            'WHERE'   => $conditions,
            'GROUPBY' => ['glpi_groups.id'],
            'ORDERBY' => [new QueryExpression('COUNT(*) DESC')],
            'LIMIT'   => 10,
        ]);
        return iterator_to_array($rows, false);
    }

    public static function computeFiltered(array $f): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => [
                'vw_uxknowdash_metrics.itilcategories_id',
                new QueryExpression('COALESCE(glpi_itilcategories.completename, CONCAT(\'Cat #\', vw_uxknowdash_metrics.itilcategories_id)) AS ' . $DB->quoteName('cat_name')),
                new QueryExpression('COUNT(*) AS '                     . $DB->quoteName('volume')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.is_fcr)*100,1) AS '  . $DB->quoteName('fcr_pct')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.kb_used)*100,1) AS ' . $DB->quoteName('kb_usage_pct')),
                new QueryExpression('ROUND(AVG(vw_uxknowdash_metrics.kb_quality),2) AS '  . $DB->quoteName('kb_quality_avg')),
            ],
            'FROM'      => 'vw_uxknowdash_metrics',
            'LEFT JOIN' => [
                'glpi_tickets'        => ['FKEY' => ['glpi_tickets'        => 'id', 'vw_uxknowdash_metrics' => 'id']],
                'glpi_itilcategories' => ['FKEY' => ['glpi_itilcategories' => 'id', 'vw_uxknowdash_metrics' => 'itilcategories_id']],
            ],
            'WHERE'   => self::buildConditions($f),
            'GROUPBY' => ['vw_uxknowdash_metrics.itilcategories_id'],
            'ORDERBY' => [new QueryExpression('COUNT(*) DESC')],
        ]);
        return iterator_to_array($rows, false);
    }
}