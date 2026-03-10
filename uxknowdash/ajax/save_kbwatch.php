<?php
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', UPDATE);
$kbwatch = new PluginUxknowdashKBWatch();
$input = [
    'name'              => strip_tags($_POST['name'] ?? ''),
    'kb_tag'            => strip_tags($_POST['kb_tag'] ?? ''),
    'entities_id'       => intval($_POST['entities_id'] ?? Session::getActiveEntity()),
    'itilcategories_id' => intval($_POST['itilcategories_id'] ?? 0),
    'is_active'         => intval($_POST['is_active'] ?? 1),
];
$id = $kbwatch->add($input);
echo $id ? 'OK:' . $id : 'ERROR';