<?php
/**
 * ajax/rate_kb.php — UXKnowDash v1.2.1
 * Endpoint POST — enregistre la note d'un utilisateur sur un article KB.
 */
include('../../../inc/includes.php');
Session::checkLoginUser();

header('Content-Type: application/json');

$knowbaseitems_id = intval($_POST['knowbaseitems_id'] ?? 0);
$rating           = intval($_POST['rating']           ?? 0);
$tickets_id       = intval($_POST['tickets_id']       ?? 0) ?: null;

if ($knowbaseitems_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_KB_ID']);
    exit;
}

try {
    $users_id = Session::getLoginUserID();
    $ok       = PluginUxknowdashKBRating::saveRating($knowbaseitems_id, $users_id, $rating, $tickets_id);
    $stats    = PluginUxknowdashKBRating::getStats($knowbaseitems_id);
    echo json_encode([
        'ok'        => $ok,
        'avg'       => $stats['avg'],
        'count'     => $stats['count'],
        'my_rating' => $rating,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
}