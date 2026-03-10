<?php
function plugin_version_uxknowdash() {
    return [
        'name'           => 'UXKnowDash',
        'version'        => '1.1.0',
        'author'         => 'Alexandre THEBAUD',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://example.com',
        'minGlpiVersion' => '11.0.0',
        'autoload'       => true,
    ];
}

function plugin_init_uxknowdash() {
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['uxknowdash'] = true;
    $PLUGIN_HOOKS['menu_toadd']['uxknowdash'] = ['tools' => 'uxknowdash'];
}