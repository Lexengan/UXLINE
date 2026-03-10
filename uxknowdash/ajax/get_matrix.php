<?php
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
$entity = intval($_GET['entity'] ?? Session::getActiveEntity());
header('Content-Type: application/json');
echo json_encode(PluginUxknowdashMatrix::compute($entity));