<?php
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header('UXKnowDash – Configuration', $_SERVER['PHP_SELF'], 'tools', 'PluginUxknowdashMenu');

if (isset($_POST['save'])) {
    PluginUxknowdashSettings::set('cron_hour',
        strip_tags($_POST['cron_hour'] ?? '02:00'));
    PluginUxknowdashSettings::set('enable_kb_prompt_on_resolve',
        isset($_POST['enable_kb_prompt_on_resolve']) ? '1' : '0');
    PluginUxknowdashSettings::set('extra_field',
        strip_tags($_POST['extra_field'] ?? ''));
    Session::addMessageAfterRedirect('Configuration enregistrée.', true, INFO);
    Html::redirect($_SERVER['PHP_SELF']);
}

$cron_hour   = PluginUxknowdashSettings::get('cron_hour', '02:00');
$extra_field = PluginUxknowdashSettings::get('extra_field', '');
$prompt      = PluginUxknowdashSettings::get('enable_kb_prompt_on_resolve', '1') === '1';

echo "<form method='POST' action=''>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card mt-3 p-3'>";
echo "<h2>Paramètres UXKnowDash</h2>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><td>Heure batch KPI</td>";
echo "<td><input type='time' name='cron_hour' value='" . Html::cleanInputText($cron_hour) . "'></td></tr>";
echo "<tr><td>Incitation liaison KB à la résolution</td>";
echo "<td><input type='checkbox' name='enable_kb_prompt_on_resolve' " . ($prompt ? 'checked' : '') . "></td></tr>";
echo "<tr><td>Champ additionnel (glpi_tickets.*)</td><td>";
echo "<select name='extra_field'><option value=''>-- aucun --</option>";
global $DB;
foreach ($DB->listFields('glpi_tickets') as $f => $def) {
    $sel = ($extra_field === $f) ? 'selected' : '';
    echo "<option value='" . Html::cleanInputText($f) . "' $sel>" . Html::cleanInputText($f) . "</option>";
}
echo "</select></td></tr>";
echo "</table>";
echo "<div class='center mt-3'>";
echo "<button class='btn btn-primary' type='submit' name='save'>Enregistrer</button>";
echo "</div>";
echo "<hr>";
echo "<div class='mt-3 d-flex gap-2'>";
echo "<a class='btn btn-secondary' href='groupcategories.form.php'>Catégories N1/N2/N3</a>";
echo "<a class='btn btn-secondary' href='itiltargets.form.php'>Objectifs ITIL</a>";
echo "</div>";
echo "</div>";
Html::closeForm();
Html::footer();