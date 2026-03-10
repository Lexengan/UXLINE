<?php
/**
 * ajax/get_kb_widget.php
 * Retourne le HTML du widget de notation KB pour injection JS.
 */
include('../../../inc/includes.php');
Session::checkLoginUser();

$knowbaseitems_id = intval($_GET['knowbaseitems_id'] ?? 0);

if ($knowbaseitems_id <= 0) {
    http_response_code(400);
    echo json_encode(['html' => '']);
    exit;
}

global $CFG_GLPI;
header('Content-Type: application/json');
echo json_encode([
    'html'       => PluginUxknowdashKBRating::renderWidget($knowbaseitems_id, $CFG_GLPI['root_doc']),
    'csrf'       => Session::getNewCSRFToken(true),
    'csrf_track' => Session::getNewCSRFToken(true),
]);