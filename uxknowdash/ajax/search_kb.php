<?php
/**
 * ajax/search_kb.php
 * Recherche plein-texte dans les articles KB accessibles à l'utilisateur.
 * Méthode : GET
 * Paramètres : q (string, mot-clé de recherche)
 *              entity (int, optionnel, entité active par défaut)
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
 
$q      = strip_tags(trim($_GET['q'] ?? ''));
$entity = intval($_GET['entity'] ?? Session::getActiveEntity());
 
if (strlen($q) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}
 
global $DB;
$rows = $DB->request([
    'SELECT' => ['id', 'name'],
    'FROM'   => 'glpi_knowbaseitems',
    'WHERE'  => [
        'is_deleted' => 0,
        ['name', 'LIKE', '%' . $DB->escape($q) . '%'],
    ],
    'ORDERBY' => ['name ASC'],
    'LIMIT'   => 20,
]);
 
$results = [];
foreach ($rows as $row) {
    $results[] = ['id' => (int)$row['id'], 'name' => $row['name']];
}
 
header('Content-Type: application/json');
echo json_encode($results);