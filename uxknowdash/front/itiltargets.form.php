<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header('UXKnowDash – Objectifs ITIL', $_SERVER['PHP_SELF'], 'tools', 'PluginUxknowdashMenu');

$entity = Session::getActiveEntity();

if (isset($_POST['save'])) {
    $cats = $_POST['cats'] ?? [];
    foreach ($cats as $catId => $vals) {
        PluginUxknowdashCategoryTarget::saveTarget(
            $entity,
            intval($catId),
            (float)($vals['fcr']     ?? 0.8),
            (float)($vals['kb']      ?? 0.6),
            (float)($vals['quality'] ?? 3.5)
        );
    }
    Session::addMessageAfterRedirect('Objectifs enregistrés.', true, INFO);
    Html::redirect($_SERVER['PHP_SELF']);
}

global $DB;
$categories = iterator_to_array($DB->request([
    'SELECT'  => ['id', 'completename'],
    'FROM'    => 'glpi_itilcategories',
    'WHERE'   => ['entities_id' => $entity, 'is_helpdeskvisible' => 1],
    'ORDERBY' => ['completename ASC'],
]), false);

$targets = [];
foreach (PluginUxknowdashCategoryTarget::getTargets($entity) as $t) {
    $targets[$t['itilcategories_id']] = $t;
}

echo "<form method='POST' action=''>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card mt-3 p-3'>";
echo "<h2>Objectifs par catégorie ITIL</h2>";
echo "<p class='text-muted'>Définissez les objectifs FCR, utilisation KB et qualité KB par catégorie.</p>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th>Catégorie</th><th>Objectif FCR (%)</th><th>Objectif KB usage (%)</th><th>Objectif qualité KB (/5)</th></tr>";

foreach ($categories as $cat) {
    $t   = $targets[$cat['id']] ?? [];
    $fcr = $t['target_fcr']        ?? 0.8;
    $kb  = $t['target_kb_usage']   ?? 0.6;
    $q   = $t['target_kb_quality'] ?? 3.5;
    $id  = intval($cat['id']);
    $name = Html::cleanInputText($cat['completename']);
    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td><input type='number' name='cats[$id][fcr]' value='" . round($fcr * 100) . "' min='0' max='100' style='width:70px'> %</td>";
    echo "<td><input type='number' name='cats[$id][kb]'  value='" . round($kb * 100)  . "' min='0' max='100' style='width:70px'> %</td>";
    echo "<td><input type='number' name='cats[$id][quality]' value='$q' min='0' max='5' step='0.1' style='width:70px'> /5</td>";
    echo "</tr>";
}

echo "</table>";
echo "<div class='center mt-3'>";
echo "<button class='btn btn-primary' type='submit' name='save'>Enregistrer</button> ";
echo "<a class='btn btn-secondary' href='config.php'>Retour</a>";
echo "</div></div>";
Html::closeForm();
Html::footer();