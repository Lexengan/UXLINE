<?php
/**
 * setup.php — UXKnowDash v1.5.0
 *
 * RÈGLE GLPI 11 : Ce fichier contient UNIQUEMENT :
 *   - plugin_version_uxknowdash()   : déclaration du plugin
 *   - plugin_init_uxknowdash()      : enregistrement de TOUS les $PLUGIN_HOOKS
 *   - plugin_uxknowdash_install()   : création des tables SQL
 *   - plugin_uxknowdash_uninstall() : suppression des tables SQL
 *
 * Les fonctions CALLBACK des hooks sont dans hook.php.
 * NE PAS déplacer les $PLUGIN_HOOKS dans hook.php — ils ne seraient jamais appelés.
 */

// ── Déclaration du plugin ─────────────────────────────────────────────────
function plugin_version_uxknowdash() {
    return [
        'name'           => 'UXKnowDash',
        'version'        => '1.5.0',
        'author'         => 'Alexandre THEBAUD',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://example.com',
        'minGlpiVersion' => '11.0.0',
        'autoload'       => true,
    ];
}

// ── Prérequis ────────────────────────────────────────────────────────────
function plugin_uxknowdash_check_prerequisites() {
    if (version_compare(GLPI_VERSION, '11.0.0', 'lt')) {
        echo 'Ce plugin nécessite GLPI >= 11.0.0';
        return false;
    }
    return true;
}

// ── Initialisation des hooks ──────────────────────────────────────────────
function plugin_init_uxknowdash() {
    global $PLUGIN_HOOKS;
    // Inclusion explicite de hook.php (GLPI 11 ne l'inclut pas automatiquement)
    include_once(__DIR__ . '/hook.php');
    // Sécurité CSRF (obligatoire GLPI 11)
    $PLUGIN_HOOKS['csrf_compliant']['uxknowdash'] = true;

    // Menu Outils : deux entrées — Tableau de bord + Liste KB
    // RÈGLE GLPI 11 : une classe par entrée, toutes dans un tableau
    $PLUGIN_HOOKS['menu_toadd']['uxknowdash'] = [
        'tools' => ['PluginUxknowdashMenu', 'PluginUxknowdashMenuKblist']
    ];

    // JS global — chargé sur toutes les pages GLPI
    // RÈGLE : doit être ici dans plugin_init_* — jamais dans hook.php ou postinit
    // RÈGLE : le fichier physique doit être dans public/plugins/uxknowdash/js/
    $PLUGIN_HOOKS['add_javascript']['uxknowdash'] = 'js/uxknowdash.js';
}

// ── Installation ──────────────────────────────────────────────────────────
function plugin_uxknowdash_install() {
    global $DB;

    // ── Schéma complet v1.5.0 (idempotent — IF NOT EXISTS partout) ────────
    $sql_file = GLPI_ROOT . '/plugins/uxknowdash/sql/mysql/plugin_uxknowdash-1.0.0.sql';
    $DB->runFile($sql_file);

    // ── Copie JS vers public/ (obligatoire GLPI 11) ───────────────────────
    $src  = GLPI_ROOT . '/plugins/uxknowdash/js/uxknowdash.js';
    $dest_dir = GLPI_ROOT . '/public/plugins/uxknowdash/js/';
    $dest = $dest_dir . 'uxknowdash.js';
    if (file_exists($src) && !file_exists($dest)) {
        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        copy($src, $dest);
    }

    // ── Droits : READ+UPDATE pour Super-Admin, READ pour les autres ───────
    $profiles = $DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']);
    foreach ($profiles as $profile) {
        $rights = ($profile['id'] == 4) ? (READ | UPDATE) : READ;
        // INSERT IGNORE : ne pas écraser si déjà présent
        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => $profile['id'],
                'name'        => 'plugin_uxknowdash_view',
            ],
            'LIMIT'  => 1,
        ]);
        if ($existing->count() === 0) {
            $DB->insert('glpi_profilerights', [
                'profiles_id' => $profile['id'],
                'name'        => 'plugin_uxknowdash_view',
                'rights'      => $rights,
            ]);
        }
    }

    return true;
}

// ── Désinstallation ───────────────────────────────────────────────────────
function plugin_uxknowdash_uninstall() {
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
        $DB->request(['FROM' => 'information_schema.TABLES',
            'WHERE' => ['TABLE_NAME' => $t]]);
        $DB->doQuery("DROP TABLE IF EXISTS `$t`");
    }
    $DB->doQuery("DROP VIEW IF EXISTS `vw_uxknowdash_metrics`");

    $DB->delete('glpi_profilerights', ['name' => 'plugin_uxknowdash_view']);

    return true;
}
