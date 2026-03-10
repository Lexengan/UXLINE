<?php
/**
 * ajax/track_kb_view.php — UXKnowDash v1.4.0
 * Enregistre une consultation d'article KB.
 * Appelé par uxknowdash.js à chaque ouverture d'article.
 */
include('../../../inc/includes.php');
Session::checkLoginUser();
if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {} // ajax

header('Content-Type: application/json');

$knowbaseitems_id = intval($_POST['knowbaseitems_id'] ?? 0);

if ($knowbaseitems_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'INVALID_KB_ID']);
    exit;
}

try {
    global $DB;
    $users_id = (int) Session::getLoginUserID();

    // Evite les doublons sur la même session : une seule entrée par utilisateur par heure
    $existing = $DB->request([
        'SELECT' => ['id'],
        'FROM'   => 'glpi_plugin_uxknowdash_kbviews',
        'WHERE'  => [
            'knowbaseitems_id' => $knowbaseitems_id,
            'users_id'         => $users_id,
            new QueryExpression('date_view > DATE_SUB(NOW(), INTERVAL 1 HOUR)'),
        ],
        'LIMIT'  => 1,
    ]);

    if ($existing->count() === 0) {
        $DB->insert('glpi_plugin_uxknowdash_kbviews', [
            'knowbaseitems_id' => $knowbaseitems_id,
            'users_id'         => $users_id,
        ]);
    }

    echo json_encode(['ok' => true]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
