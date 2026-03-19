<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header('UXKnowDash – Catégories N1/N2/N3', Plugin::getWebDir('uxknowdash') . '/front/groupcategories.form.php', 'tools', 'PluginUxknowdashMenu');

$entity = Session::getActiveEntity();

if (isset($_POST['save'])) {
    global $DB;
    // Supprimer les anciens enregistrements de l'entité
    $DB->delete('glpi_plugin_uxknowdash_groupcategories', ['entities_id' => $entity]);
    // Réinsérer
    $groups = $_POST['groups'] ?? [];
    foreach ($groups as $level => $groupIds) {
        foreach ((array)$groupIds as $gid) {
            $gid = intval($gid);
            if ($gid <= 0) continue;
            $DB->insert('glpi_plugin_uxknowdash_groupcategories', [
                'entities_id' => $entity,
                'groups_id'   => $gid,
                'level'       => strip_tags($level),
            ]);
        }
    }
    Session::addMessageAfterRedirect('Catégories enregistrées.', true, INFO);
    Html::redirect(Plugin::getWebDir('uxknowdash') . '/front/groupcategories.form.php');
}

// Charger les groupes GLPI disponibles
global $DB;
$glpiGroups = iterator_to_array($DB->request([
    'SELECT'  => ['id', 'name'],
    'FROM'    => 'glpi_groups',
    'WHERE'   => ['entities_id' => $entity, 'is_assign' => 1],
    'ORDERBY' => ['name ASC'],
]), false);

// Charger les affectations actuelles
$current = [];
$rows = $DB->request([
    'SELECT' => ['groups_id', 'level'],
    'FROM'   => 'glpi_plugin_uxknowdash_groupcategories',
    'WHERE'  => ['entities_id' => $entity],
]);
foreach ($rows as $row) {
    $current[$row['level']][] = $row['groups_id'];
}

echo "<form method='POST' action=''>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card mt-3 p-3'>";
echo "<h2>Association Groupes ↔ Niveaux N1/N2/N3/Expert</h2>";
echo "<p class='text-muted'>Sélectionnez les groupes correspondant à chaque niveau de support.</p>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th>Niveau</th><th>Groupes affectés</th></tr>";

foreach (['N1', 'N2', 'N3', 'Expert'] as $level) {
    $selected = $current[$level] ?? [];
    echo "<tr><td><strong>$level</strong></td><td>";
    echo "<select name='groups[$level][]' multiple size='5' style='width:100%;min-width:300px'>";
    foreach ($glpiGroups as $g) {
        $sel = in_array($g['id'], $selected) ? 'selected' : '';
        echo "<option value='" . intval($g['id']) . "' $sel>" . Html::cleanInputText($g['name']) . "</option>";
    }
    echo "</select></td></tr>";
}

echo "</table>";
echo "<div class='center mt-3'>";
echo "<button class='btn btn-primary' type='submit' name='save'>Enregistrer</button> ";
echo "<a class='btn btn-secondary' href='config.php'>Retour</a>";
echo "</div></div>";
Html::closeForm();
Html::footer();