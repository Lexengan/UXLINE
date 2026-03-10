<?php
/**
 * ajax/unlink_kb.php
 * Supprime le lien entre un ticket et un article KB.
 * Méthode : POST
 * Paramètres : tickets_id (int), knowbaseitems_id (int)
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', UPDATE);
 
$tickets_id       = intval($_POST['tickets_id']       ?? 0);
$knowbaseitems_id = intval($_POST['knowbaseitems_id'] ?? 0);
 
header('Content-Type: application/json');
 
if ($tickets_id <= 0 || $knowbaseitems_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_PARAMS']);
    exit;
}
 
$link = new KnowbaseItem_Item();
$rows = $link->find([
    'knowbaseitems_id' => $knowbaseitems_id,
    'itemtype'         => 'Ticket',
    'items_id'         => $tickets_id,
]);
 
foreach ($rows as $row) {
    $link->delete(['id' => $row['id']]);
}
 
echo json_encode(['ok' => true]);