<?php

class PluginUxknowdashBuildKpis extends CronTask {

   static function getTypeName($nb = 0) {
      return "UXKnowDash KPI Builder";
   }

   function getDescription($id) {
      return "Compute UXKnowDash KPI cache";
   }

   function run($task) {

      file_put_contents(
         GLPI_ROOT . "/files/_log/uxknowdash_test.log",
         date('Y-m-d H:i:s') . " CRON RUN\n",
         FILE_APPEND
      );

      return 1;
   }
}