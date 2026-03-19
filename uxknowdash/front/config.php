<?php
/**
 * front/config.php — UXKnowDash
 * Page de configuration du plugin.
 */
include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

Html::header(
    __('UXKnowDash – Configuration', 'uxknowdash'),
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginUxknowdashMenu'
);

if (isset($_POST['save'])) {
    // ── Validation et sanitisation des entrées POST ───────────────────────

    // cron_hour : format HH:MM strict
    $cron_hour_raw = $_POST['cron_hour'] ?? '02:00';
    $cron_hour     = preg_match('/^\d{2}:\d{2}$/', $cron_hour_raw) ? $cron_hour_raw : '02:00';

    // extra_field : doit être un nom de colonne réel de glpi_tickets
    global $DB;
    $valid_fields  = array_keys($DB->listFields('glpi_tickets'));
    $extra_raw     = $_POST['extra_field'] ?? '';
    $extra_field   = in_array($extra_raw, $valid_fields, true) ? $extra_raw : '';

    // enable_kb_prompt_on_resolve : booléen
    $prompt_value  = isset($_POST['enable_kb_prompt_on_resolve']) ? '1' : '0';

    PluginUxknowdashSettings::set('cron_hour',                    $cron_hour);
    PluginUxknowdashSettings::set('enable_kb_prompt_on_resolve',  $prompt_value);
    PluginUxknowdashSettings::set('extra_field',                   $extra_field);

    Session::addMessageAfterRedirect(
        __('Configuration enregistrée.', 'uxknowdash'),
        true,
        INFO
    );
    Html::redirect(Plugin::getWebDir('uxknowdash') . '/front/config.php');
}

global $DB;
$cron_hour   = PluginUxknowdashSettings::get('cron_hour', '02:00');
$extra_field = PluginUxknowdashSettings::get('extra_field', '');
$prompt      = PluginUxknowdashSettings::get('enable_kb_prompt_on_resolve', '1') === '1';

echo "<form method='POST' action=''>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card mt-3 p-3'>";
echo "<h2>" . __('Paramètres UXKnowDash', 'uxknowdash') . "</h2>";
echo "<table class='tab_cadre_fixe'>";

echo "<tr><td>" . __('Heure batch KPI', 'uxknowdash') . "</td>";
echo "<td><input type='time' name='cron_hour' value='" . Html::cleanInputText($cron_hour) . "'></td></tr>";

echo "<tr><td>" . __('Incitation liaison KB à la résolution', 'uxknowdash') . "</td>";
echo "<td><input type='checkbox' name='enable_kb_prompt_on_resolve' " . ($prompt ? 'checked' : '') . "></td></tr>";

echo "<tr><td>" . __('Champ additionnel (glpi_tickets.*)', 'uxknowdash') . "</td><td>";
echo "<select name='extra_field'>";
echo "<option value=''>-- " . __('aucun', 'uxknowdash') . " --</option>";
foreach ($DB->listFields('glpi_tickets') as $f => $def) {
    $sel = ($extra_field === $f) ? 'selected' : '';
    echo "<option value='" . Html::cleanInputText($f) . "' {$sel}>" . Html::cleanInputText($f) . "</option>";
}
echo "</select></td></tr>";

echo "</table>";
echo "<div class='center mt-3'>";
echo "<button class='btn btn-primary' type='submit' name='save'>" . __('Enregistrer', 'uxknowdash') . "</button>";
echo "</div>";
echo "<hr>";
echo "<div class='mt-3 d-flex gap-2'>";
echo "<a class='btn btn-secondary' href='groupcategories.form.php'>" . __('Catégories N1/N2/N3', 'uxknowdash') . "</a>";
echo "<a class='btn btn-secondary' href='itiltargets.form.php'>" . __('Objectifs ITIL', 'uxknowdash') . "</a>";
echo "</div>";
echo "</div>";
Html::closeForm();
Html::footer();