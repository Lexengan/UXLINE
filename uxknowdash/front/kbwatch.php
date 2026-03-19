<?php
declare(strict_types=1);

include('../../../inc/includes.php');
Session::checkLoginUser();

Html::header('UXKnowDash', Plugin::getWebDir('uxknowdash') . '/front/kbwatch.php', 'tools', 'uxknowdash');

$tpl = new \Glpi\Application\View\TemplateRenderer();

echo $tpl->render('plugins/uxknowdash/dashboard.html.twig', [
   'title' => 'UXKnowDash Dashboard'
]);

Html::footer();