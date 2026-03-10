<?php
/**
 * ajax/get_kblist.php — UXKnowDash v1.4.0
 * Liste paginée KB + export CSV.
 * Colonnes : id, nom, créateur, catégorie KB, catégories ITIL, note, tickets, assets,
 *            dernière révision, vues, dernière consultation (< 3 mois ?)
 */
include('../../../inc/includes.php');
Session::checkLoginUser();
header('Content-Type: application/json');

global $DB;

$sort    = in_array($_GET['sort'] ?? '', ['id','name','creator','kb_category','itil_categories','avg_rating','date_mod','view','nb_tickets','nb_assets','last_view']) ? $_GET['sort'] : 'id';
$order   = ($_GET['order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perpage = max(1, min(100, intval($_GET['perpage'] ?? 25)));
$offset  = ($page - 1) * $perpage;
$export  = ($_GET['export'] ?? '') === 'csv';

try {
    // ── 1. Articles KB ────────────────────────────────────────────────────
    $kb_rows = iterator_to_array($DB->request([
        'SELECT' => ['id','name','users_id','forms_categories_id','date_mod','view'],
        'FROM'   => 'glpi_knowbaseitems',
        'WHERE'  => [new QueryExpression('1=1')],
    ]));

    $kb_ids = array_column($kb_rows, 'id');
    if (!$kb_ids) {
        echo json_encode(['total' => 0, 'rows' => [], 'error' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── 2. Utilisateurs ───────────────────────────────────────────────────
    $user_ids = array_unique(array_column($kb_rows, 'users_id'));
    $users    = [];
    foreach ($DB->request(['SELECT' => ['id','firstname','realname'], 'FROM' => 'glpi_users', 'WHERE' => ['id' => $user_ids]]) as $u) {
        $users[$u['id']] = trim($u['firstname'] . ' ' . $u['realname']);
    }

    // ── 3. Catégories KB ──────────────────────────────────────────────────
    $kb_cat_links = [];
    foreach ($DB->request([
        'SELECT' => ['knowbaseitems_id','knowbaseitemcategories_id'],
        'FROM'   => 'glpi_knowbaseitems_knowbaseitemcategories',
        'WHERE'  => ['knowbaseitems_id' => $kb_ids],
    ]) as $row) {
        $kb_cat_links[$row['knowbaseitems_id']][] = $row['knowbaseitemcategories_id'];
    }
    $all_cat_ids  = array_unique(array_merge(...array_values($kb_cat_links ?: [[]])));
    $kb_cat_names = [];
    if ($all_cat_ids) {
        foreach ($DB->request(['SELECT' => ['id','name'], 'FROM' => 'glpi_knowbaseitemcategories', 'WHERE' => ['id' => $all_cat_ids]]) as $c) {
            $kb_cat_names[$c['id']] = $c['name'];
        }
    }

    // ── 4. Liaisons items (tickets + assets) ──────────────────────────────
    $nb_tickets       = array_fill_keys($kb_ids, 0);
    $nb_assets        = array_fill_keys($kb_ids, 0);
    $ticket_ids_by_kb = [];
    foreach ($DB->request([
        'SELECT' => ['knowbaseitems_id','itemtype','items_id'],
        'FROM'   => 'glpi_knowbaseitems_items',
        'WHERE'  => ['knowbaseitems_id' => $kb_ids],
    ]) as $row) {
        $kbid = $row['knowbaseitems_id'];
        if ($row['itemtype'] === 'Ticket') {
            $nb_tickets[$kbid] = ($nb_tickets[$kbid] ?? 0) + 1;
            $ticket_ids_by_kb[$kbid][] = $row['items_id'];
        } else {
            $nb_assets[$kbid] = ($nb_assets[$kbid] ?? 0) + 1;
        }
    }

    // ── 5. Catégories ITIL ────────────────────────────────────────────────
    $all_ticket_ids     = array_unique(array_merge(...array_values($ticket_ids_by_kb ?: [[]])));
    $itil_cat_by_ticket = [];
    $itil_cat_names     = [];
    if ($all_ticket_ids) {
        foreach ($DB->request(['SELECT' => ['id','itilcategories_id'], 'FROM' => 'glpi_tickets', 'WHERE' => ['id' => $all_ticket_ids]]) as $t) {
            $itil_cat_by_ticket[$t['id']] = $t['itilcategories_id'];
        }
        $itil_cat_ids = array_filter(array_unique(array_values($itil_cat_by_ticket)));
        if ($itil_cat_ids) {
            foreach ($DB->request(['SELECT' => ['id','completename'], 'FROM' => 'glpi_itilcategories', 'WHERE' => ['id' => $itil_cat_ids]]) as $ic) {
                $itil_cat_names[$ic['id']] = $ic['completename'];
            }
        }
    }

    // ── 6. Notes UXKnowDash ───────────────────────────────────────────────
    $ratings = [];
    foreach ($DB->request([
        'SELECT'  => [
            'knowbaseitems_id',
            new QueryExpression('AVG(rating) AS ' . $DB->quoteName('avg_rating')),
            new QueryExpression('COUNT(id) AS '   . $DB->quoteName('vote_count')),
        ],
        'FROM'    => 'glpi_plugin_uxknowdash_kbratings',
        'WHERE'   => ['knowbaseitems_id' => $kb_ids, new QueryExpression('rating > 0')],
        'GROUPBY' => ['knowbaseitems_id'],
    ]) as $r) {
        $ratings[$r['knowbaseitems_id']] = [
            'avg'   => round((float)$r['avg_rating'], 2),
            'count' => (int)$r['vote_count'],
        ];
    }

    // ── 7. Dernière consultation (glpi_plugin_uxknowdash_kbviews) ─────────
    $last_views = [];
    foreach ($DB->request([
        'SELECT'  => [
            'knowbaseitems_id',
            new QueryExpression('MAX(date_view) AS ' . $DB->quoteName('last_view')),
        ],
        'FROM'    => 'glpi_plugin_uxknowdash_kbviews',
        'WHERE'   => ['knowbaseitems_id' => $kb_ids],
        'GROUPBY' => ['knowbaseitems_id'],
    ]) as $v) {
        $last_views[$v['knowbaseitems_id']] = $v['last_view'];
    }
    $three_months_ago = strtotime('-3 months');

    // ── 8. Assemblage ─────────────────────────────────────────────────────
    $rows = [];
    foreach ($kb_rows as $kb) {
        $kbid    = $kb['id'];
        $creator = $users[$kb['users_id']] ?? '';

        $cat_ids_for_kb = $kb_cat_links[$kbid] ?? [];
        $kb_category    = implode(', ', array_filter(array_map(fn($cid) => $kb_cat_names[$cid] ?? '', $cat_ids_for_kb)));

        $itil_cats = [];
        foreach ($ticket_ids_by_kb[$kbid] ?? [] as $tid) {
            $icid = $itil_cat_by_ticket[$tid] ?? 0;
            if ($icid && isset($itil_cat_names[$icid])) $itil_cats[$icid] = $itil_cat_names[$icid];
        }
        $itil_categories = implode(', ', array_unique($itil_cats));

        // Dernière consultation
        $last_view_raw  = $last_views[$kbid] ?? null;
        $last_view_str  = $last_view_raw ? date('d/m/Y H:i', strtotime($last_view_raw)) : null;
        $recent         = $last_view_raw ? (strtotime($last_view_raw) >= $three_months_ago) : false;

        // Filtre recherche
        if ($search !== '') {
            $haystack = strtolower($kb['name'] . ' ' . $creator . ' ' . $kb_category . ' ' . $itil_categories);
            if (strpos($haystack, strtolower($search)) === false) continue;
        }

        $rows[] = [
            'id'               => (int)$kbid,
            'name'             => $kb['name'] ?? '',
            'creator'          => $creator,
            'kb_category'      => $kb_category,
            'itil_categories'  => $itil_categories,
            'avg_rating'       => $ratings[$kbid]['avg']   ?? 0,
            'vote_count'       => $ratings[$kbid]['count'] ?? 0,
            'date_mod'         => $kb['date_mod'] ? date('d/m/Y H:i', strtotime($kb['date_mod'])) : '',
            'view'             => (int)$kb['view'],
            'nb_tickets'       => $nb_tickets[$kbid] ?? 0,
            'nb_assets'        => $nb_assets[$kbid]  ?? 0,
            'last_view'        => $last_view_str,
            'last_view_recent' => $recent,
        ];
    }

    // ── 9. Tri ────────────────────────────────────────────────────────────
    usort($rows, function($a, $b) use ($sort, $order) {
        $va = $a[$sort] ?? '';
        $vb = $b[$sort] ?? '';
        $cmp = is_numeric($va) && is_numeric($vb) ? ($va <=> $vb) : strcmp((string)$va, (string)$vb);
        return $order === 'DESC' ? -$cmp : $cmp;
    });

    $total = count($rows);

    // ── 10. Export CSV ────────────────────────────────────────────────────
    if ($export) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="uxknowdash_kb_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID','Nom','Créateur','Catégorie KB','Catégories ITIL','Note moy.','Nb votes','Dernière révision','Vues','Nb tickets liés','Nb assets liés','Dernière consultation','Consultée < 3 mois'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['name'], $r['creator'], $r['kb_category'],
                $r['itil_categories'], $r['avg_rating'], $r['vote_count'],
                $r['date_mod'], $r['view'], $r['nb_tickets'], $r['nb_assets'],
                $r['last_view'] ?? 'Jamais', $r['last_view_recent'] ? 'Oui' : 'Non',
            ], ';');
        }
        fclose($out);
        exit;
    }

    // ── 11. Pagination ────────────────────────────────────────────────────
    $rows = array_slice($rows, $offset, $perpage);
    echo json_encode(['total' => $total, 'rows' => $rows, 'error' => null], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['total' => 0, 'rows' => [], 'error' => $e->getMessage() . ' L' . $e->getLine()]);
}
