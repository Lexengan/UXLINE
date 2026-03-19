-- ============================================================
-- UXKnowDash — Migration v1.3.0 -> v1.6.0
-- ============================================================
-- Corrections :
--   1. Ajout colonne `level` manquante dans groupcategories
--      (causait : Unknown column 'level' in 'SELECT')
--   2. Ajout colonne `entities_id` manquante dans groupcategories
--      (nécessaire pour le filtrage par entité)
--   3. Ajout index sur (entities_id, level)
-- ============================================================
-- Idempotent : ADD COLUMN IF NOT EXISTS — peut être rejoué
--              sans erreur sur une installation déjà migrée
-- ============================================================

-- 1. Colonne `level` — valeurs : N1, N2, N3, Expert
ALTER TABLE `glpi_plugin_uxknowdash_groupcategories`
    ADD COLUMN IF NOT EXISTS `level` varchar(20) NOT NULL DEFAULT 'N1'
    COMMENT 'Niveau de support : N1, N2, N3, Expert';

-- 2. Colonne `entities_id` — FK vers glpi_entities.id
ALTER TABLE `glpi_plugin_uxknowdash_groupcategories`
    ADD COLUMN IF NOT EXISTS `entities_id` int NOT NULL DEFAULT 0
    COMMENT 'FK -> glpi_entities.id';

-- 3. Index composite pour les requêtes de filtrage par entité et niveau
ALTER TABLE `glpi_plugin_uxknowdash_groupcategories`
    ADD INDEX IF NOT EXISTS `idx_entity_level` (`entities_id`, `level`);