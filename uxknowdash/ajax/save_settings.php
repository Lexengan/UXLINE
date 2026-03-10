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

global $DB;
header('Content-Type: application/json; charset=utf-8');

$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to'] ?? null;

$fcrMode  = strtoupper($_GET['fcr_mode'] ?? 'A');
if (!in_array($fcrMode, ['A','B','C'], true)) $fcrMode = 'A';
$fcrCol = match ($fcrMode) {
   'B' => 'kc.fcr_b',
   'C' => 'kc.fcr_c',
   default => 'kc.fcr_a'
};

$where = [];
$where[] = "1=1";
$where[] = \Entity::getEntitiesRestrictRequest('AND', 'kc', 'entities_id');

if ($dateFrom) $where[] = "kc.date_creation >= '".$DB->escape($dateFrom)." 00:00:00'";
if ($dateTo)   $where[] = "kc.date_creation <= '".$DB->escape($dateTo)." 23:59:59'";

$sql = "
SELECT
   kc.itilcategories_id,
   ic.completename AS itil_name,
   COUNT(*) AS volume,
   AVG($fcrCol) AS fcr_rate,
   SUM(CASE WHEN kc.kb_link_count > 0 THEN 1 ELSE 0 END) AS with_kb,
   AVG(kc.kb_avg_rating) AS kb_avg_rating,
   t.target_fcr_a, t.target_fcr_b, t.target_fcr_c,
   t.volume_threshold, t.min_kb_quality
FROM glpi_plugin_uxknowdash_kpi_cache kc
LEFT JOIN glpi_itilcategories ic ON ic.id = kc.itilcategories_id
LEFT JOIN glpi_plugin_uxknowdash_itil_targets t ON t.itilcategories_id = kc.itilcategories_id
WHERE ".implode(' AND ', $where)."
GROUP BY kc.itilcategories_id, ic.completename,
         t.target_fcr_a, t.target_fcr_b, t.target_fcr_c,
         t.volume_threshold, t.min_kb_quality
HAVING volume >= COALESCE(t.volume_threshold, 50)
ORDER BY volume DESC
LIMIT 200
";

$items = [];
$it = $DB->request($sql);

foreach ($it as $r) {
   $volume = (int)$r['volume'];
   $fcr = (float)($r['fcr_rate'] ?? 0);
   $withKb = (int)($r['with_kb'] ?? 0);
   $kbUsage = $volume > 0 ? ($withKb / $volume) : 0.0;
   $kbAvg = $r['kb_avg_rating'] !== null ? (float)$r['kb_avg_rating'] : null;

   $target = null;
   if ($fcrMode === 'B') $target = $r['target_fcr_b'];
   elseif ($fcrMode === 'C') $target = $r['target_fcr_c'];
   else $target = $r['target_fcr_a'];

   $target = ($target !== null && $target !== '') ? ((float)$target / 100.0) : null;

   $minQ = $r['min_kb_quality'] !== null ? (float)$r['min_kb_quality'] : null;

   $fcrGap = ($target !== null) ? max(0.0, $target - $fcr) : 0.0;
   $kbGap  = ($minQ !== null && $kbAvg !== null) ? max(0.0, $minQ - $kbAvg) : max(0.0, 0.3 - $kbUsage);

   // Score gap simple : pondération volume
   $score = ($volume * (0.7 * $fcrGap + 0.3 * $kbGap));

   $items[] = [
      'itilcategories_id' => (int)$r['itilcategories_id'],
      'itil_name'         => $r['itil_name'] ?? ('#'.$r['itilcategories_id']),
      'volume'            => $volume,
      'fcr'               => $fcr,
      'target'            => $target,
      'kb_usage'          => $kbUsage,
      'kb_avg_rating'     => $kbAvg,
      'score'             => $score
   ];
}

// tri score desc + top 10
usort($items, fn($a,$b) => ($b['score'] <=> $a['score']));
$items = array_slice($items, 0, 10);

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);