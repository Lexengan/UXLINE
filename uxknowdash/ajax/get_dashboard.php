<?php
/**
 * ajax/get_dashboard.php
 * Endpoint JSON pour le dashboard UXKnowDash.
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
header('Content-Type: application/json; charset=utf-8');

$f = [
    'entity'    => isset($_REQUEST['entity'])    ? intval($_REQUEST['entity'])    : Session::getActiveEntity(),
    'date_from' => isset($_REQUEST['date_from']) ? preg_replace('/[^0-9\-]/', '', $_REQUEST['date_from']) : '',
    'date_to'   => isset($_REQUEST['date_to'])   ? preg_replace('/[^0-9\-]/', '', $_REQUEST['date_to'])   : '',
    'category'  => isset($_REQUEST['category'])  ? intval($_REQUEST['category'])  : 0,
    'group'     => isset($_REQUEST['group'])      ? intval($_REQUEST['group'])     : 0,
];

try {
    echo json_encode([
        'success'       => true,
        'filters'       => $f,
        'kpis'          => PluginUxknowdashMatrix::getKpis($f),
        'volume_by_day' => PluginUxknowdashMatrix::getVolumeByDay($f),
        'fcr_by_cat'    => PluginUxknowdashMatrix::getFcrByCategory($f),
        'kb_by_cat'     => PluginUxknowdashMatrix::getKbUsageByCategory($f),
        'status_pie'    => PluginUxknowdashMatrix::getStatusPie($f),
        'kb_distrib'    => PluginUxknowdashMatrix::getKbQualityDistrib($f),
        'by_group'      => PluginUxknowdashMatrix::getVolumeByGroup($f),
        'table'         => PluginUxknowdashMatrix::computeFiltered($f),
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}