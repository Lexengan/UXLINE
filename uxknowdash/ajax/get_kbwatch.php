<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

if (!Session::haveRightsOr(\GlpiPlugin\Uxknowdash\Rights::RIGHT_READ, [READ, CREATE, UPDATE])
    && !Session::haveRight('config', UPDATE)) {
   http_response_code(403);
   header('Content-Type: application/json; charset=utf-8');
   echo json_encode(['error' => 'forbidden']);
   exit;
}

use GlpiPlugin\Uxknowdash\Settings;

global $DB;

header('Content-Type: application/json; charset=utf-8');

// Group categories
$groupcats = [];
$it = $DB->request([
   'FROM'  => 'glpi_plugin_uxknowdash_groupcategories',
   'WHERE' => ['is_active' => 1],
   'ORDER' => 'name ASC'
]);
foreach ($it as $r) {
   $groupcats[] = ['code' => $r['code'], 'name' => $r['name']];
}

// Focus sets
$focussets = [];
$it = $DB->request([
   'FROM'  => 'glpi_plugin_uxknowdash_focus_sets',
   'WHERE' => ['is_active' => 1],
   'ORDER' => 'name ASC'
]);
foreach ($it as $r) {
   $focussets[] = ['id' => (int)$r['id'], 'name' => $r['name']];
}

// Entities (respect droits: on renvoie seulement celles visibles à l’utilisateur)
$entities = [];
$allowed = $_SESSION['glpiactiveentities'] ?? [];
$it = $DB->request([
   'FROM'  => 'glpi_entities',
   'ORDER' => 'completename ASC'
]);
foreach ($it as $r) {
   $id = (int)$r['id'];
   if ($id === 0 || in_array($id, $allowed, true)) {
      $entities[] = ['id' => $id, 'name' => $r['completename']];
   }
}

// Locations (optionnel – on limite pour perf)
$locations = [];
if ($DB->tableExists('glpi_locations')) {
   $it = $DB->request([
      'FROM'  => 'glpi_locations',
      'ORDER' => 'completename ASC',
      'LIMIT' => 2000
   ]);
   foreach ($it as $r) {
      $locations[] = ['id' => (int)$r['id'], 'name' => $r['completename']];
   }
}

// ITIL categories
$itil = [];
$it = $DB->request([
   'FROM'  => 'glpi_itilcategories',
   'ORDER' => 'completename ASC'
]);
foreach ($it as $r) {
   $itil[] = ['id' => (int)$r['id'], 'name' => $r['completename']];
}

// Extra field
$extraField = Settings::get('extra_field', null);
$extra = ['field' => $extraField, 'values' => []];

if (is_string($extraField) && $extraField !== '') {
   // valeurs distinctes depuis kpi_cache (si déjà calculé)
   if ($DB->tableExists('glpi_plugin_uxknowdash_kpi_cache')
       && $DB->fieldExists('glpi_plugin_uxknowdash_kpi_cache', 'extra_field')
       && $DB->fieldExists('glpi_plugin_uxknowdash_kpi_cache', 'extra_value')) {

      $it = $DB->request("
         SELECT DISTINCT extra_value
         FROM glpi_plugin_uxknowdash_kpi_cache
         WHERE extra_field = '".$DB->escape($extraField)."'
           AND extra_value IS NOT NULL
         ORDER BY extra_value ASC
         LIMIT 500
      ");
      foreach ($it as $r) {
         $extra['values'][] = (string)$r['extra_value'];
      }
   }
}

echo json_encode([
   'groupcats' => $groupcats,
   'focussets' => $focussets,
   'entities'  => $entities,
   'locations' => $locations,
   'itil'      => $itil,
   'extra'     => $extra
], JSON_UNESCAPED_UNICODE);