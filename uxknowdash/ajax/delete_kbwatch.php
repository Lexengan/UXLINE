<?php
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', DELETE);
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'INVALID_ID'; exit; }
$kbwatch = new PluginUxknowdashKBWatch();
$kbwatch->delete(['id' => $id]);
echo 'OK';