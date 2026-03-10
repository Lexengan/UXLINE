<?php
/**
 * ajax/get_kb_liaison.php — UXKnowDash v1.5.0
 * Taux de liaison KB global + par technicien.
 * Périmètre :
 *   - Tickets hors statut 1 (Nouveau) : statuts 2,4,5,6,10
 *   - Technicien assigné : glpi_tickets_users type=2
 *   - KB liée : présence dans glpi_knowbaseitems_items itemtype='Ticket'
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
header('Content-Type: application/json');

global $DB;

$entity    = intval($_GET['entity']    ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';
$group     = intval($_GET['group']     ?? 0);

// Statuts "pris en charge" : tout sauf Nouveau (1)
$statuts_ok = [2, 4, 5, 6, 10];

try {
    // ── Filtres communs ───────────────────────────────────────────────────
    $where_base = [
        'glpi_tickets.is_deleted' => 0,
        'glpi_tickets.status'     => $statuts_ok,
    ];
    if ($entity > 0) $where_base['glpi_tickets.entities_id'] = $entity;
    if ($date_from) $where_base[] = new QueryExpression('DATE(glpi_tickets.date_creation) >= ' . $DB->quoteValue($date_from));
    if ($date_to)   $where_base[] = new QueryExpression('DATE(glpi_tickets.date_creation) <= ' . $DB->quoteValue($date_to));

    // ── 1. Tous les tickets pris en charge (filtrés) ──────────────────────
    $all_tickets = [];
    foreach ($DB->request([
        'SELECT' => ['glpi_tickets.id'],
        'FROM'   => 'glpi_tickets',
        'WHERE'  => $where_base,
    ]) as $t) {
        $all_tickets[$t['id']] = true;
    }
    $all_ids = array_keys($all_tickets);
    $total   = count($all_ids);

    if (!$total) {
        echo json_encode(['global' => ['total' => 0, 'with_kb' => 0, 'pct' => 0], 'byTech' => [], 'error' => null]);
        exit;
    }

    // ── 2. Tickets avec KB liée ───────────────────────────────────────────
    $kb_ticket_ids = [];
    foreach ($DB->request([
        'SELECT' => ['items_id'],
        'FROM'   => 'glpi_knowbaseitems_items',
        'WHERE'  => [
            'itemtype' => 'Ticket',
            'items_id' => $all_ids,
        ],
    ]) as $row) {
        $kb_ticket_ids[$row['items_id']] = true;
    }
    $with_kb_global = count($kb_ticket_ids);
    $pct_global     = $total > 0 ? round($with_kb_global / $total * 100, 1) : 0;

    // ── 3. Techniciens assignés (type=2) ──────────────────────────────────
    $tech_tickets = []; // [users_id => [ticket_id => true]]
    $where_tech = [
        'glpi_tickets_users.type'       => 2,
        'glpi_tickets_users.tickets_id' => $all_ids,
    ];
    // Filtre groupe si demandé
    if ($group > 0) {
        $members = [];
        foreach ($DB->request([
            'SELECT' => ['users_id'],
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['groups_id' => $group],
        ]) as $m) {
            $members[] = $m['users_id'];
        }
        if ($members) $where_tech['glpi_tickets_users.users_id'] = $members;
    }

    foreach ($DB->request([
        'SELECT' => ['tickets_id', 'users_id'],
        'FROM'   => 'glpi_tickets_users',
        'WHERE'  => $where_tech,
    ]) as $row) {
        $tech_tickets[$row['users_id']][$row['tickets_id']] = true;
    }

    // ── 4. Noms des techniciens ────────────────────────────────────────────
    $tech_ids   = array_keys($tech_tickets);
    $tech_names = [];
    if ($tech_ids) {
        foreach ($DB->request([
            'SELECT' => ['id', 'firstname', 'realname'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['id' => $tech_ids],
        ]) as $u) {
            $tech_names[$u['id']] = trim($u['firstname'] . ' ' . $u['realname']) ?: 'User#' . $u['id'];
        }
    }

    // ── 5. Calcul par technicien ──────────────────────────────────────────
    $by_tech = [];
    foreach ($tech_tickets as $uid => $tids) {
        $nb_total   = count($tids);
        $nb_with_kb = count(array_intersect_key($tids, $kb_ticket_ids));
        $pct        = $nb_total > 0 ? round($nb_with_kb / $nb_total * 100, 1) : 0;
        $by_tech[]  = [
            'users_id' => $uid,
            'name'     => $tech_names[$uid] ?? 'User#' . $uid,
            'total'    => $nb_total,
            'with_kb'  => $nb_with_kb,
            'pct'      => $pct,
        ];
    }

    // Tri décroissant par % liaison
    usort($by_tech, fn($a, $b) => $b['pct'] <=> $a['pct']);

    echo json_encode([
        'global'  => ['total' => $total, 'with_kb' => $with_kb_global, 'pct' => $pct_global],
        'byTech'  => $by_tech,
        'error'   => null,
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['global' => null, 'byTech' => [], 'error' => $e->getMessage() . ' L' . $e->getLine()]);
}
