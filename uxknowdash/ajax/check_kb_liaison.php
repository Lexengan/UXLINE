<?php
/**
 * ajax/check_kb_liaison.php — UXKnowDash v1.5.0
 * Vérifie si un ticket donné a au moins un article KB associé.
 * Appelé en GET depuis le JS injecté sur l'onglet Solution.
 *
 * Paramètre GET : tickets_id (int)
 * Réponse JSON  : { "has_kb": bool, "count": int }
 */

// ── Includes GLPI (3 niveaux depuis ajax/) ────────────────────────────────
include('../../../inc/includes.php');

// ── Auth minimale ─────────────────────────────────────────────────────────
Session::checkLoginUser();

header('Content-Type: application/json; charset=utf-8');

// ── Paramètre ─────────────────────────────────────────────────────────────
$tickets_id = (int) ($_GET['tickets_id'] ?? 0);
if ($tickets_id <= 0) {
    echo json_encode(['has_kb' => false, 'count' => 0, 'error' => 'tickets_id manquant']);
    exit;
}

// ── Vérification KB liée ──────────────────────────────────────────────────
// Source : glpi_knowbaseitems_items (itemtype='Ticket', items_id = tickets_id)
// Référence GLPI : même table que dans ajax/get_kb_liaison.php
global $DB;

$iter = $DB->request([
    'COUNT'  => 'cnt',
    'FROM'   => 'glpi_knowbaseitems_items',
    'WHERE'  => [
        'itemtype' => 'Ticket',
        'items_id' => $tickets_id,
    ],
]);

$count = 0;
foreach ($iter as $row) {
    $count = (int) $row['cnt'];
}

echo json_encode([
    'has_kb' => $count > 0,
    'count'  => $count,
]);
