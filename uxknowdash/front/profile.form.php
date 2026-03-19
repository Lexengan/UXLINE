<?php
/**
 * front/profile.form.php — UXKnowDash v1.5.7
 *
 * Pattern EXACT de impactauto/front/profile.form.php (plugin fonctionnel confirmé).
 *
 * Délègue entièrement à Profile->update($_POST) du core GLPI.
 * Profile::update() gère glpi_profilerights nativement pour tous les droits
 * déclarés dans $_POST['_rights'], y compris ceux des plugins.
 *
 * Source : impactauto/front/profile.form.php
 */
include('../../../inc/includes.php');

Session::checkRight('profile', UPDATE);

$profile = new Profile();

if (isset($_POST['update'])) {
    $profile->update($_POST);
    Html::back();
} else {
    Html::redirect(Plugin::getWebDir('uxknowdash') . '/front/index.php');
}
