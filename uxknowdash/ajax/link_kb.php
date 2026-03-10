<?php
/**
 * ajax/link_kb.php
 * Crée le lien entre un ticket et un article KB dans glpi_knowbaseitems_items.
 * Utilise la classe native GLPI KnowbaseItem_Item pour respecter les ACL et logs.
 * Méthode : POST
 * Paramètres : tickets_id (int), knowbaseitems_id (int)
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
 
$tickets_id       = intval($_POST['tickets_id']       ?? 0);
$knowbaseitems_id = intval($_POST['knowbaseitems_id'] ?? 0);
 
header('Content-Type: application/json');
 
if ($tickets_id <= 0 || $knowbaseitems_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_PARAMS']);
    exit;
}
 
// Vérifier que le ticket existe et est accessible
$ticket = new Ticket();
if (!$ticket->getFromDB($tickets_id) || !$ticket->canView()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'TICKET_ACCESS_DENIED']);
    exit;
}
 
// Utiliser la classe GLPI native pour créer le lien
// Cela déclenche les logs natifs et respecte les droits GLPI
$link = new KnowbaseItem_Item();
$existing = $link->find([
    'knowbaseitems_id' => $knowbaseitems_id,
    'itemtype'         => 'Ticket',
    'items_id'         => $tickets_id,
]);
 
if (!empty($existing)) {
    echo json_encode(['ok' => true, 'already_linked' => true]);
    exit;
}
 
$id = $link->add([
    'knowbaseitems_id' => $knowbaseitems_id,
    'itemtype'         => 'Ticket',
    'items_id'         => $tickets_id,
]);
 
echo json_encode(['ok' => $id > 0]);