<?php
/**
 * inc/profile.class.php — UXKnowDash v1.5.7
 *
 * Pattern EXACT de impactauto/src/Profile.php (plugin fonctionnel confirmé).
 *
 * Points clés :
 * 1. extends Profile (classe core GLPI), pas CommonDBTM
 * 2. Charger le profil via $coreProfile = new \Profile(); getFromDB(); $this->fields = $coreProfile->fields
 *    → displayRightsChoiceMatrix lit $this->fields[$rightname] pour pré-cocher les cases
 *    → Sans ce chargement : $this->fields vide → cases toujours décochées
 * 3. displayRightsChoiceMatrix avec 'itemtype' => PluginUxknowdashMenu (pas 'rights' explicite)
 *    → GLPI résout les droits disponibles via PluginUxknowdashMenu::$rightname
 * 4. Form poste vers Profile core (getFormURL du core) avec Html::hidden('id') + Html::closeForm()
 *    → Profile::update() du core gère glpi_profilerights nativement
 * 5. front/profile.form.php délègue à Profile->update($_POST) — pas de logique custom
 * 6. changeProfile() vide — GLPI recharge les droits automatiquement après le hook
 *
 * Source : impactauto/src/Profile.php — vérifié fonctionnel sur GLPI 11
 */
class PluginUxknowdashProfile extends Profile
{
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('id')) {
            return self::createTabEntry('UXKnowDash');
        }
        return '';
    }

    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if ($item instanceof Profile) {
            $profile = new self();
            $profile->showFormUxknowdash((int) $item->getID());
        }
        return true;
    }

    public function showFormUxknowdash(int $profiles_id): void
    {
        if (!Session::haveRight('profile', READ)) {
            return;
        }

        // Charger le profil core pour peupler $this->fields.
        // displayRightsChoiceMatrix lit $this->fields['plugin_uxknowdash_view']
        // pour savoir quelles cases cocher. Sans ce chargement, $this->fields
        // est vide et toutes les cases sont décochées.
        // On ne peut pas appeler $this->getFromDB() directement car notre classe
        // n'a pas de table → on instancie Profile core et on copie ses fields.
        // Source : impactauto/src/Profile.php showFormImpactauto()
        $coreProfile = new Profile();
        $coreProfile->getFromDB($profiles_id);
        $this->fields = $coreProfile->fields;

        $can_edit = Session::haveRight('profile', UPDATE);

        echo "<div class='spaced'>";

        if ($can_edit) {
            // Poster vers l'endpoint du PLUGIN (pas vers le core GLPI directement).
            // Le POST vers le core depuis un contexte Ajax plugin déclenche
            // AccessDeniedHttpException dans CheckCsrfListener.
            // L'endpoint du plugin est couvert par csrf_compliant=true et
            // délègue ensuite à Profile->update($_POST) pour l'écriture en base.
            // Source : impactauto/src/Profile.php + front/profile.form.php
            $form_url = Plugin::getWebDir('uxknowdash') . '/front/profile.form.php';
            echo "<form method='post' action='" . htmlspecialchars($form_url) . "'>";
        }

        // 'itemtype' => PluginUxknowdashMenu permet à GLPI de résoudre
        // PluginUxknowdashMenu::$rightname = 'plugin_uxknowdash_view'
        // et d'utiliser getRights() pour les droits disponibles.
        // Source : impactauto/src/Profile.php + example/src/Profile.php
        $rights = [
            [
                'itemtype' => 'PluginUxknowdashMenu',
                'label'    => __('UXKnowDash — Dashboard & KB List', 'uxknowdash'),
                'field'    => 'plugin_uxknowdash_view',
            ],
        ];

        $this->displayRightsChoiceMatrix($rights, [
            'canedit' => $can_edit,
            'title'   => __('UXKnowDash', 'uxknowdash'),
        ]);

        if ($can_edit) {
            echo "<div class='text-center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm(); // Gère le CSRF automatiquement
        }

        echo '</div>';
    }

    public static function addDefaultProfileRights(): void
    {
        ProfileRight::addProfileRights(['plugin_uxknowdash_view']);
    }

    public static function removeProfileRights(): void
    {
        ProfileRight::deleteProfileRights(['plugin_uxknowdash_view']);
    }

    public static function changeProfile(): void
    {
        // GLPI recharge les droits automatiquement après ce hook.
        // Aucune logique supplémentaire nécessaire.
        // Source : impactauto/hook.php plugin_change_profile_impactauto()
    }
}
