<?php
/**
 * manifest.php — UXKnowDash
 *
 * Chargé par le routeur Symfony de GLPI 11 AVANT setup.php pour enregistrer le plugin.
 * Doit contenir UNIQUEMENT plugin_version_uxknowdash() — pas de plugin_init_*().
 * La version doit être identique à celle de setup.php.
 */
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
