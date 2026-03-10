-- update-1.2.0-1.3.0.sql — UXKnowDash v1.5.0
-- Ajoute kb_liaison_invite dans settings si absent (idempotent)
-- La colonne existait déjà visuellement dans config.php mais sans effet fonctionnel

ALTER TABLE `glpi_plugin_uxknowdash_settings`
    ADD COLUMN IF NOT EXISTS `kb_liaison_invite` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Afficher incitation KB lors de la résolution d un ticket';

-- S'assurer qu'il existe bien une ligne de settings (INSERT si vide)
INSERT IGNORE INTO `glpi_plugin_uxknowdash_settings` (`id`) VALUES (1);
