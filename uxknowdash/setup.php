<?php
/**
 * setup.php — UXKnowDash v1.5.0
 */

// ── Déclaration du plugin ─────────────────────────────────────────────────
function plugin_version_uxknowdash()
{
    return [
        'name'           => 'UXKnowDash',
        'version'        => '1.6.0',
        'author'         => 'Alexandre THEBAUD',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://example.com',
        'minGlpiVersion' => '11.0.0',
        'autoload'       => true,
    ];
}

// ── Prérequis ─────────────────────────────────────────────────────────────
function plugin_uxknowdash_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, '11.0.0', 'lt')) {
        echo 'Ce plugin nécessite GLPI >= 11.0.0';
        return false;
    }
    return true;
}

// ── Vérification de configuration ────────────────────────────────────────
function plugin_uxknowdash_check_config()
{
    global $DB;

    $viewExists = $DB->request([
        'SELECT' => ['TABLE_NAME'],
        'FROM'   => 'information_schema.VIEWS',
        'WHERE'  => [
            'TABLE_SCHEMA' => $DB->dbdefault,
            'TABLE_NAME'   => 'vw_uxknowdash_metrics',
        ],
    ])->count() > 0;

    if (!$viewExists) {
        Session::addMessageAfterRedirect(
            __('UXKnowDash : la vue SQL vw_uxknowdash_metrics est manquante. Réinstallez le plugin.', 'uxknowdash'),
            false,
            ERROR
        );
        return false;
    }

    return true;
}

// ── Initialisation des hooks ──────────────────────────────────────────────
function plugin_init_uxknowdash()
{
    global $PLUGIN_HOOKS;

    include_once(__DIR__ . '/hook.php');

    // Sécurité CSRF (obligatoire GLPI 11)
    $PLUGIN_HOOKS['csrf_compliant']['uxknowdash'] = true;

    // ── Enregistrement de la classe Profile ──────────────────────────────
    // Ajoute un onglet "UXKnowDash" sur chaque fiche Administration > Profils
    Plugin::registerClass('PluginUxknowdashProfile', ['addtabon' => ['Profile']]);

    // GLPI recharge les droits automatiquement après ce hook.
    // Source : impactauto/setup.php + hook.php plugin_change_profile vide
    $PLUGIN_HOOKS['change_profile']['uxknowdash'] = 'plugin_change_profile_uxknowdash';

    // Menu Outils
    // menu_toadd : une string (nom de classe) par section — pas un tableau de classes.
    // Source : example/setup.php → ['tools' => Example::class]
    // PluginUxknowdashMenu::getMenuContent() retourne les deux entrées (Dashboard + KB List).
    $PLUGIN_HOOKS['menu_toadd']['uxknowdash'] = [
        'tools' => 'PluginUxknowdashMenu',
    ];

    // JS global — doit être dans public/ (GLPI 11)
    $PLUGIN_HOOKS['add_javascript']['uxknowdash'] = 'js/uxknowdash.js';
}

// ── Installation ──────────────────────────────────────────────────────────
function plugin_uxknowdash_install()
{
    global $DB;

    // Schéma complet (idempotent — IF NOT EXISTS)
    $sql_file = GLPI_ROOT . '/plugins/uxknowdash/sql/mysql/plugin_uxknowdash-1.0.0.sql';
    $DB->runFile($sql_file);

    // ── Copie JS vers public/ ─────────────────────────────────────────────
    $src      = GLPI_ROOT . '/plugins/uxknowdash/js/uxknowdash.js';
    $dest_dir = GLPI_ROOT . '/public/plugins/uxknowdash/js/';
    $dest     = $dest_dir . 'uxknowdash.js';
    if (file_exists($src)) {
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        copy($src, $dest);
    }

    // ── Droits — méthode officielle GLPI ─────────────────────────────────
    // Idempotent : on ne crée les lignes que si le droit n'existe pas encore
    // dans glpi_profilerights (cas d'une réinstallation sans désinstallation préalable).
    // ProfileRight::addProfileRights() fait un INSERT brut et lève une erreur SQL
    // sur la contrainte d'unicité (profiles_id, name) si la ligne existe déjà.
    $rightExists = $DB->request([
        'SELECT' => ['id'],
        'FROM'   => 'glpi_profilerights',
        'WHERE'  => ['name' => 'plugin_uxknowdash_view'],
        'LIMIT'  => 1,
    ])->current();

    if (!$rightExists) {
        // Étape 1 : crée le droit pour tous les profils existants (valeur 0)
        PluginUxknowdashProfile::addDefaultProfileRights();

        // Étape 2 : accorde ALLSTANDARDRIGHT aux profils super-admin
        $migration = new Migration('1.6.0');
        $migration->addRight(
            'plugin_uxknowdash_view',
            ALLSTANDARDRIGHT,
            ['config' => UPDATE]
        );
    }

    return true;
}

// ── Désinstallation ───────────────────────────────────────────────────────
function plugin_uxknowdash_uninstall()
{
    global $DB;

    $tables = [
        'glpi_plugin_uxknowdash_settings',
        'glpi_plugin_uxknowdash_matrixcache',
        'glpi_plugin_uxknowdash_groupcategories',
        'glpi_plugin_uxknowdash_focusprofiles',
        'glpi_plugin_uxknowdash_category_targets',
        'glpi_plugin_uxknowdash_kbwatch',
        'glpi_plugin_uxknowdash_kbratings',
        'glpi_plugin_uxknowdash_kbviews',
    ];

    foreach ($tables as $t) {
        $DB->doQuery("DROP TABLE IF EXISTS `{$t}`");
    }
    $DB->doQuery('DROP VIEW IF EXISTS `vw_uxknowdash_metrics`');

    // Supprime le droit de tous les profils via l'API officielle
    PluginUxknowdashProfile::removeProfileRights();

    // Nettoyage du fichier JS dans public/
    $dest = GLPI_ROOT . '/public/plugins/uxknowdash/js/uxknowdash.js';
    if (file_exists($dest)) {
        unlink($dest);
    }

    return true;
}
