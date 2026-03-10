<?php
/**
 * ajax/get_kb_rating.php
 * Endpoint AJAX — Lit les stats de notation d'un article KB + note perso.
 * Méthode : GET
 * Paramètres GET :
 *   knowbaseitems_id  (int, obligatoire)
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
 
$knowbaseitems_id = intval($_GET['knowbaseitems_id'] ?? 0);
 
if ($knowbaseitems_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_KB_ID']);
    exit;
}
 
$users_id  = Session::getLoginUserID();
$stats     = PluginUxknowdashKBRating::getStats($knowbaseitems_id);
$myRating  = PluginUxknowdashKBRating::getUserRating($knowbaseitems_id, $users_id);
 
header('Content-Type: application/json');
echo json_encode([
    'ok'        => true,
    'avg'       => $stats['avg'],
    'count'     => $stats['count'],
    'my_rating' => $myRating,
]);